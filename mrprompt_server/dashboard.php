<?php
// Dane pulpitu strony startowej: ostatnie projekty, zadania, porządki, ulubione, pliki, stan serwera.
require_once __DIR__ . '/db_init.php';
require_once __DIR__ . '/tree_render.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$root = $_SERVER['DOCUMENT_ROOT'];
$realRoot = realpath($root);

// Zmiana stanu zadania z pulpitu (bez wchodzenia w projekt)
if (($_POST['action'] ?? '') === 'toggle_todo') {
    mrp_guard();
    $path = realpath($_POST['path'] ?? '');
    $idx = (int)($_POST['idx'] ?? -1);
    $file = $path ? $path . DIRECTORY_SEPARATOR . 'todo.json' : '';
    if (!$path || strpos($path, $realRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
        echo json_encode(['status' => 'error']);
        exit;
    }
    $todos = json_decode(file_get_contents($file), true);
    if (!is_array($todos) || !isset($todos[$idx])) {
        echo json_encode(['status' => 'error']);
        exit;
    }
    $todos[$idx]['done'] = !($todos[$idx]['done'] ?? false);
    file_put_contents($file, json_encode($todos, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['status' => 'ok']);
    exit;
}

function newestFiles($dir, $limit, $depth = 0, &$out = [])
{
    if ($depth > 4) return $out;
    foreach (@scandir($dir) ?: [] as $it) {
        if ($it === '.' || $it === '..' || $it[0] === '.' || in_array($it, TREE_SKIP, true)) continue;
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_link($p)) continue;
        if (is_dir($p)) newestFiles($p, $limit, $depth + 1, $out);
        else $out[] = [(int)@filemtime($p), $p];
    }
    return $out;
}

$favs = [];
try {
    $favs = $db->query('SELECT path FROM favorites')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
}
$favNorm = array_map(fn($p) => str_replace('\\', '/', strtolower($p)), $favs);

$projects = [];
$todos = [];
foreach (@scandir($root) ?: [] as $name) {
    if ($name === '.' || $name === '..' || $name[0] === '.' || in_array($name, TREE_SKIP, true)) continue;
    $path = $root . DIRECTORY_SEPARATOR . $name;
    if (!is_dir($path)) continue;

    $mtime = newestMtimeCached($path);
    $info = getProjectInfo($path) ?: [];
    $short = trim($info['short'] ?? '');
    $hasNotes = is_file($path . DIRECTORY_SEPARATOR . 'notes.json') || is_file($path . DIRECTORY_SEPARATOR . 'notes.md');
    $isFav = in_array(str_replace('\\', '/', strtolower($path)), $favNorm, true);

    $open = 0;
    $todoFile = $path . DIRECTORY_SEPARATOR . 'todo.json';
    if (is_file($todoFile)) {
        $list = json_decode(@file_get_contents($todoFile), true);
        if (is_array($list)) {
            foreach ($list as $i => $t) {
                if (empty($t['done']) && trim($t['text'] ?? '') !== '') {
                    $open++;
                    $todos[] = ['project' => $name, 'path' => $path, 'idx' => $i, 'text' => $t['text']];
                }
            }
        }
    }

    $projects[] = [
        'name' => $name, 'path' => $path, 'mtime' => $mtime, 'short' => $short,
        'github' => (preg_match('~^https?://~i', $info['github_url'] ?? '') ? $info['github_url'] : ''),
        'notes' => $hasNotes, 'open_todos' => $open, 'fav' => $isFav,
    ];
}

usort($projects, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
$now = time();

// 1. Wróć do pracy
$recent = array_slice($projects, 0, 6);

// 5. Do uporządkowania: aktywne projekty bez opisu (najpierw), potem dawno nieruszane
$attention = [];
foreach ($projects as $p) {
    $age = $now - $p['mtime'];
    $noDesc = $p['short'] === '';
    $stale = $age > 180 * 86400;
    if (!$noDesc && !$stale) continue;
    $why = [];
    if ($noDesc) $why[] = 'brak opisu';
    if (!$p['notes']) $why[] = 'brak notatek';
    if ($stale) $why[] = 'nieruszany od ' . floor($age / 86400) . ' dni';
    // priorytet: 0 = używany ostatnio, ale bez opisu; 1 = pozostałe bez opisu; 2 = tylko nieruszany
    $prio = $noDesc ? ($age < 60 * 86400 ? 0 : 1) : 2;
    $attention[] = ['name' => $p['name'], 'path' => $p['path'], 'why' => $why, 'prio' => $prio, 'mtime' => $p['mtime']];
}
usort($attention, fn($x, $y) => [$x['prio'], -$x['mtime']] <=> [$y['prio'], -$y['mtime']]);
$attentionTotal = count($attention);
$attention = array_slice($attention, 0, 8);

// 6. Ostatnio zmieniane pliki (skan tylko 5 najświeższych projektów)
$files = [];
foreach (array_slice($projects, 0, 5) as $p) newestFiles($p['path'], 8, 0, $files);
usort($files, fn($a, $b) => $b[0] <=> $a[0]);
$recentFiles = [];
foreach (array_slice($files, 0, 8) as [$mt, $fp]) {
    $rel = ltrim(str_replace('\\', '/', substr($fp, strlen($root))), '/');
    $recentFiles[] = ['rel' => $rel, 'abs' => str_replace('\\', '/', $fp), 'time' => $mt];
}

// 4. Stan serwera (lekki)
function portUp($port)
{
    $fp = @fsockopen('127.0.0.1', $port, $e, $s, 0.25);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}
$ds = @disk_total_space('.') ?: 0;
$df = @disk_free_space('.') ?: 0;
$server = [
    'php' => PHP_VERSION,
    'apache' => true,
    'mysql' => portUp(3306),
    'disk_used' => $ds > 0 ? round(($ds - $df) / $ds * 100) : 0,
    'disk_free_gb' => round($df / 1073741824, 1),
];

echo json_encode([
    'now' => $now,
    'total' => count($projects),
    'recent' => $recent,
    'todos' => array_slice($todos, 0, 40),
    'todos_total' => count($todos),
    'attention' => $attention,
    'attention_total' => $attentionTotal,
    'favorites' => array_values(array_filter($projects, fn($p) => $p['fav'])),
    'files' => $recentFiles,
    'server' => $server,
    'names' => array_map(fn($p) => ['name' => $p['name'], 'path' => $p['path']], $projects),
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
