<?php
// Zwraca HTML jednego poziomu drzewa (leniwe ładowanie podfolderów).
require_once __DIR__ . '/db_init.php';
require_once __DIR__ . '/tree_render.php';

$path = realpath($_GET['path'] ?? '');
$docRoot = realpath($_SERVER['DOCUMENT_ROOT']);

if (!$path || !is_dir($path) || !$docRoot || ($path !== $docRoot && strpos($path, $docRoot . DIRECTORY_SEPARATOR) !== 0)) {
    http_response_code(400);
    exit;
}

$favPaths = [];
try {
    $favPaths = $db->query("SELECT path FROM favorites")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
}

header('Content-Type: text/html; charset=utf-8');
$top = !empty($_GET['top']);
if ($top && strcasecmp($path, $docRoot) === 0) $path = $_SERVER['DOCUMENT_ROOT']; // ta sama forma ścieżek co przy pierwszym renderze
echo renderTree($path, $favPaths, $top);
