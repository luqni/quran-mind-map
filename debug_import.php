<?php

$xmlPath = '/home/luqni/Project/quran-map/storage/app/raw_data/extracted_content.xml';

if (!file_exists($xmlPath)) {
    die("File not found\n");
}

echo "Reading file...\n";
$xmlContent = file_get_contents($xmlPath);

// Apply regex fixes
$xmlContent = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xmlContent);
$xmlContent = preg_replace('/(\s)\w+:/', '$1', $xmlContent);

echo "Parsing XML...\n";
$xml = simplexml_load_string($xmlContent);

if ($xml === false) {
    die("Failed to parse XML\n");
}

$count = 0;
foreach ($xml->xpath('//wbody/wp') as $paragraph) {
    $text = '';
    foreach ($paragraph->xpath('.//wt') as $t) {
        $text .= (string)$t;
    }
    $text = trim($text);
    
    if (empty($text)) continue;

    $indent = 0;
    // Try both paths just in case
    $pPr = $paragraph->xpath('wpPr/wind');
    
    if (!empty($pPr)) {
        $attrs = $pPr[0]->attributes();
        echo "Found wind tag. Attributes: " . json_encode($attrs) . "\n";
        
        if (isset($attrs['left'])) {
            $indent = (int)$attrs['left'];
        } elseif (isset($attrs['wleft'])) { // Check if regex fail
             $indent = (int)$attrs['wleft'];
        }
    } else {
        echo "No wind tag found.\n";
    }

    $level = floor($indent / 360);
    echo "Text: " . substr($text, 0, 50) . "... | Indent: $indent | Level: $level\n";

    $count++;
    if ($count > 20) break; 
}
