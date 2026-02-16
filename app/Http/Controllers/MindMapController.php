<?php

namespace App\Http\Controllers;

use App\Models\Surah;
use App\Models\MindMapNode;
use Illuminate\Http\Request;

class MindMapController extends Controller
{
    public function index()
    {
        $surahs = Surah::orderBy('number')->get();
        return view('mindmap', compact('surahs'));
    }

    public function getSurahData($id)
    {
        $surah = Surah::with('nodes')->findOrFail($id);

        // Build Tree Structure
        $root = [
            'name' => "Surat " . $surah->name,
            'english_name' => $surah->english_name,
            'children' => []
        ];

        $nodes = $surah->nodes->groupBy('parent_id');

        // Recursive function to build hierarchy
        $buildTree = function ($parentId) use ($nodes, &$buildTree) {
            $children = [];
            if (isset($nodes[$parentId])) {
                foreach ($nodes[$parentId] as $node) {
                    $child = [
                        'name' => $node->label,
                        'ayah_range' => $node->ayah_start ? "Ayat {$node->ayah_start}-{$node->ayah_end}" : null,
                        'children' => $buildTree($node->id)
                    ];
                    // If no children, remove key to keep D3 clean (optional, but good for some layouts)
                    if (empty($child['children'])) {
                        unset($child['children']);
                    }
                    $children[] = $child;
                }
            }
            return $children;
        };

        // Root nodes have parent_id = null
        $root['children'] = $buildTree(null);

        return response()->json($root);
    }
}
