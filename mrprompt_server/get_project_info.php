<?php
$path = $_GET['path'] ?? '';
$jsonFile = $path . DIRECTORY_SEPARATOR . '.mrp_info.json';
$notesFile = $path . DIRECTORY_SEPARATOR . 'notes.md';
$todoFile = $path . DIRECTORY_SEPARATOR . 'todo.json';

header('Content-Type: application/json');

$response = [
    'short' => '',
    'created' => date('Y-m-d'),
    'github_url' => '',
    'notes' => '',
    'todo' => []
];

// 1. Load JSON Metadata
if (file_exists($jsonFile)) {
    $meta = json_decode(file_get_contents($jsonFile), true);
    if ($meta) {
        $response['short'] = $meta['short'] ?? '';
        $response['created'] = $meta['created'] ?? date('Y-m-d');
        $response['github_url'] = $meta['github_url'] ?? '';
        // Legacy support: if 'full' exists in JSON but no notes.md, use it as notes
        if (!file_exists($notesFile) && !empty($meta['full'])) {
            $response['notes'] = $meta['full'];
        }
    }
}

// 2. Load Notes (JSON or Legacy Markdown)
$notesJsonFile = $path . DIRECTORY_SEPARATOR . 'notes.json';
if (file_exists($notesJsonFile)) {
    $response['notes_list'] = json_decode(file_get_contents($notesJsonFile), true) ?? [];
} elseif (file_exists($notesFile)) {
    // Legacy support: wrap existing notes.md in a list structure
    $content = file_get_contents($notesFile);
    if (!empty($content)) {
        $response['notes_list'] = [[
            'id' => 'legacy',
            'title' => 'Notatki (Starsze)',
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s', filemtime($notesFile))
        ]];
    }
} else {
    $response['notes_list'] = [];
}

// 3. Load TODOs
if (file_exists($todoFile)) {
    $todos = json_decode(file_get_contents($todoFile), true);
    if (is_array($todos)) {
        $response['todo'] = $todos;
    }
}

echo json_encode($response);
