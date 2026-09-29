<?php
// Lekki status do dolnego paska (odpytywany co ~30 s): porty usług, miejsce na dysku, wersja PHP.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', 0);

function portUp($port)
{
    $fp = @fsockopen('127.0.0.1', $port, $e, $s, 0.25);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

$total = @disk_total_space('.') ?: 0;
$free = @disk_free_space('.') ?: 0;

echo json_encode([
    'apache' => true, // skoro to odpowiada, Apache działa
    'mysql' => portUp(3306),
    'php' => PHP_VERSION,
    'disk_used' => $total > 0 ? round(($total - $free) / $total * 100) : 0,
    'disk_free_gb' => round($free / 1073741824, 1),
    'ts' => time(),
]);
