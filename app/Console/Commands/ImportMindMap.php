<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Surah;
use App\Models\MindMapNode;

class ImportMindMap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:mindmap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Quran Mind Map data from extracted XML content';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $xmlPath = storage_path('app/raw_data/extracted_content.xml');

        if (!file_exists($xmlPath)) {
            $this->error("File not found: $xmlPath");
            return;
        }

        $this->info("Parsing XML...");
        MindMapNode::truncate();
        
        // Read file content
        $xmlContent = file_get_contents($xmlPath);
        
        // Remove namespaces to make simplexml handling easier
        // 1. Remove tag prefixes: <w:p> -> <wp>
        $xmlContent = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xmlContent);
        // 2. Remove attribute prefixes: w:val="X" -> val="X"
        // 2. Remove attribute prefixes: w:val="X" -> val="X"
        // Improved regex to only match attributes (word followed by =")
        $xmlContent = preg_replace('/(\s)\w+:(\w+=")/', '$1$2', $xmlContent);
        
        $xml = simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            $this->error("Failed to parse XML");
            return;
        }

        $currentSurah = null;
        $nodeStack = []; // Stack to keep track of parent nodes based on hierarchy level
        
        $foundFirstSurah = false;
        $surahCounter = 0; // Counter for surah number

        // Iterate through paragraphs (assuming w:body -> w:p)
        foreach ($xml->xpath('//wbody/wp') as $paragraph) {
            // Extract text
            $text = '';
            foreach ($paragraph->xpath('.//wt') as $t) {
                $text .= (string)$t;
            }
            
            // Text cleaning
            $cleanText = trim($text);
            if (empty($cleanText)) continue;

            // Check for Word List Indent Level (w:ilvl)
            $ilvl = null;
            $numPr = $paragraph->xpath('wpPr/wnumPr/wilvl');
            if (!empty($numPr)) {
                $ilvl = (int)$numPr[0]['val'];
            }
            
            // Get indentation (existing logic)
            // After attribute regex strip, it should be 'left' not 'wleft' or 'w:left'
            $indent = 0;
            $pPr = $paragraph->xpath('wpPr/wind');
            if (!empty($pPr)) {
                $indentAttr = $pPr[0]->attributes();
                if (isset($indentAttr['left'])) {
                    $indent = (int)$indentAttr['left'];
                }
            }

            $this->info("Processing: $text (Indent: $indent, ilvl: " . ($ilvl ?? 'null') . ")");

            // --- LEVEL CODE DETECTION (PRIORITY) ---
            // Check for explicit level codes: [L1], [L2], [L3], [L4]
            $computedLevel = 1; // Default to Level 1
            
            if (preg_match('/^\[L(\d+)\]/', $text, $matches)) {
                // Extract level from [L1], [L2], etc.
                $computedLevel = (int)$matches[1];
                // Remove the level code from text for cleaner processing
                $text = preg_replace('/^\[L\d+\]\s*/', '', $text);
            }
            // --- ILVL-BASED HIERARCHY LOGIC (FALLBACK) ---
            // Use Word's internal list level (ilvl) if no level code found
            elseif ($ilvl !== null) {
                // Use ilvl directly: ilvl 0 = Level 1, ilvl 1 = Level 2, etc.
                $computedLevel = $ilvl + 1;
            } 
            // --- SURAT DETECTION (FALLBACK) ---
            elseif (preg_match('/^Surat\s/i', $text)) {
                $computedLevel = 1;
            }


            $this->info("Processing: $text (Indent: $indent, Level: $computedLevel, ilvl: " . ($ilvl ?? 'null') . ")");

            // --- Parsing Logic ---

            // Check for New Surah
            if (str_contains(strtolower($text), 'surat')) {
                // Extract full Surah text as name
                // Pattern: "Surat al Baqarah 286 Ayat tentang Kekhalifahan di Bumi dan Sistematikanya"
                // Pattern: "Surat Ali 'Imran 200 Ayat..." or "Surat an-Nisa 176 Ayat..."
                // Extract short name and ayah count for matching, but store full text
                
                // Updated regex to handle all apostrophe variations, hyphens, and Unicode characters
                // Match any non-digit characters before the number
                preg_match('/Surat\s+([^\d]+?)\s+(\d+)/iu', $text, $matches);
                if (isset($matches[1])) {
                    $surahShortName = trim($matches[1]);
                    $ayahCount = isset($matches[2]) ? (int)$matches[2] : 0;
                    
                    // Use full text as the name (after removing [L1] code if present)
                    $fullSurahName = $text;
                    
                    $this->info("DEBUG: Extracted Surah - Short: '$surahShortName', Full: '$fullSurahName', Ayah Count: $ayahCount");
                    
                    // Try to match with DB using short name
                    $surah = Surah::where('name', 'like', "%$surahShortName%")->first();
                    
                    if ($surah) {
                        $this->info("Found Surah: " . $surah->name);
                        $currentSurah = $surah;
                        $foundFirstSurah = true;
                    } else {
                        // Create new Surah with full name
                        $surahCounter++;
                        $this->info("Creating new Surah: $fullSurahName (Number: $surahCounter)");
                        $currentSurah = Surah::create([
                            'number' => $surahCounter,
                            'name' => $fullSurahName,
                            'ayah_count' => $ayahCount,
                        ]);
                        $foundFirstSurah = true;
                    }
                }
            }

            if (!$foundFirstSurah) continue; // Skip content before first Surah

            // Parse Node (Ayat Range and Label)
            $ayahStart = null;
            $ayahEnd = null;
            $label = $text;
            $type = 'theme';

            // Extract Reference from label if present (e.g. "1. Ayat 1-5: Theme")
            // Remove the numbering prefix for cleaner labels
            $cleanText = preg_replace('/^(\\d+\\.|[a-z]\\.|[0-9]+\\))\\s+/i', '', $text);
            
            // Pattern: "Ayat 1-5: Theme" or "Ayat 1-5 Theme"
            if (preg_match('/Ayat\\s*(\\d+)\\s*-\\s*(\\d+)\\s*[:.]?\\s*(.*)/i', $cleanText, $matches)) {
                $ayahStart = (int)$matches[1];
                $ayahEnd = (int)$matches[2];
                $label = trim($matches[3]);
                $type = 'ayah_group';
            } 
            // Pattern: "Ayat 1: Theme"
            elseif (preg_match('/Ayat\\s*(\\d+)\\s*[:.]?\\s*(.*)/i', $cleanText, $matches)) {
                $ayahStart = (int)$matches[1];
                $ayahEnd = (int)$matches[1];
                $label = trim($matches[2]);
                $type = 'ayah_group';
            } else {
                $label = $cleanText;
            }

            // Determine Parent
            $parentId = null;
            
            // Simple level-based parent detection
            // Find nearest parent in stack with level < computedLevel
            if ($computedLevel > 1) {
                for ($i = count($nodeStack) - 1; $i >= 0; $i--) {
                    if ($nodeStack[$i]['level'] < $computedLevel) {
                        $parentId = $nodeStack[$i]['id'];
                        break;
                    }
                }
            }

            
            if ($parentId) {
                 $this->info("    -> Found Parent ID: $parentId for Level $computedLevel");
            } else if ($computedLevel > 1) {
                 $this->info("    -> NO Parent Found for Level $computedLevel (Stack size: " . count($nodeStack) . ")");
            }

            // Create Node
            if ($currentSurah) {
                $node = MindMapNode::create([
                    'surah_id' => $currentSurah->id,
                    'parent_id' => $parentId,
                    'label' => $label,
                    'ayah_start' => $ayahStart,
                    'ayah_end' => $ayahEnd,
                    'level' => $computedLevel,
                    'type' => $type,
                ]);
                
                if ($node->parent_id) {
                     $this->info("    -> Created Node {$node->id} with Parent {$node->parent_id}");
                }

                // Update Stack
                // Remove any nodes from stack that are deeper or equal to current level
                while (!empty($nodeStack) && end($nodeStack)['level'] >= $computedLevel) {
                    array_pop($nodeStack);
                }
                
                // Push current
                $nodeStack[] = [
                    'id' => $node->id,
                    'level' => $computedLevel,
                    'ayah_start' => $ayahStart,
                    'ayah_end' => $ayahEnd
                ];
            }
        }

        $this->info("Import completed!");
    }
}
