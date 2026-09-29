<?php
require_once __DIR__ . '/db_init.php';
require_once __DIR__ . '/security.php';

// Ikony dostępne w formularzu (Font Awesome 6 Free)
const APP_ICONS = [
    'fa-solid fa-globe', 'fa-solid fa-code', 'fa-solid fa-terminal', 'fa-solid fa-database',
    'fa-solid fa-server', 'fa-solid fa-folder-open', 'fa-solid fa-file-lines', 'fa-solid fa-gear',
    'fa-solid fa-chart-line', 'fa-solid fa-envelope', 'fa-solid fa-cart-shopping', 'fa-solid fa-image',
    'fa-solid fa-music', 'fa-solid fa-video', 'fa-solid fa-book', 'fa-solid fa-calendar-days',
    'fa-solid fa-robot', 'fa-solid fa-shield-halved', 'fa-solid fa-cloud', 'fa-solid fa-bolt',
    'fa-brands fa-github', 'fa-brands fa-php', 'fa-brands fa-js', 'fa-brands fa-wordpress',
];

// Zwraca poprawną klasę FA; stare emoji/grafiki są mapowane na ikonę dopasowaną do nazwy/URL
function normalizeIcon($icon, $name = '', $url = '')
{
    $icon = trim((string)$icon);
    if (preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/', $icon)) return $icon;

    $hay = strtolower($name . ' ' . $url);
    $map = [
        'phpmyadmin' => 'fa-solid fa-database', 'sql' => 'fa-solid fa-database',
        'github' => 'fa-brands fa-github', 'wordpress' => 'fa-brands fa-wordpress',
        'wp-' => 'fa-brands fa-wordpress', 'mail' => 'fa-solid fa-envelope',
        'docs' => 'fa-solid fa-book', 'blog' => 'fa-solid fa-pen-nib',
        'sklep' => 'fa-solid fa-cart-shopping', 'shop' => 'fa-solid fa-cart-shopping',
        'admin' => 'fa-solid fa-user-shield', 'api' => 'fa-solid fa-plug',
        'chat' => 'fa-solid fa-comments', 'ai' => 'fa-solid fa-robot',
    ];
    foreach ($map as $needle => $cls) {
        if (strpos($hay, $needle) !== false) return $cls;
    }
    return 'fa-solid fa-globe';
}

// Walidacja wspólna dla dodawania i edycji
function cleanAppInput()
{
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    // Dozwolone tylko http(s) i ścieżki względne – blokuje javascript: itp.
    if ($name === '' || $url === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $url)) return null;
    if (!preg_match('~^(https?:)?//|^/|^\.{0,2}/~i', $url)) $url = 'http://' . $url;
    return [$name, $url, normalizeIcon($_POST['icon'] ?? '', $name, $url)];
}

$action = $_POST['action'] ?? '';
if ($action !== '') {
    mrp_guard();
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'add' || $action === 'update') {
        $in = cleanAppInput();
        if (!$in) {
            echo json_encode(['status' => 'error', 'msg' => 'Podaj nazwę i poprawny adres URL']);
            exit;
        }
        if ($action === 'add') {
            $db->prepare('INSERT INTO apps (name, url, icon, pinned) VALUES (?, ?, ?, 0)')->execute($in);
        } else {
            $db->prepare('UPDATE apps SET name = ?, url = ?, icon = ? WHERE id = ?')->execute([...$in, $id]);
        }
    } elseif ($action === 'pin') {
        $db->prepare('UPDATE apps SET pinned = ? WHERE id = ?')->execute([empty($_POST['pinned']) ? 0 : 1, $id]);
    } elseif ($action === 'delete') {
        $db->prepare('DELETE FROM apps WHERE id = ?')->execute([$id]);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Nieznana akcja']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'apps' => getApps($db)], JSON_UNESCAPED_UNICODE);
    exit;
}

// Funkcja pobierająca listę (do użycia w index.php)
function getApps($db)
{
    $apps = $db->query("SELECT * FROM apps ORDER BY name COLLATE NOCASE ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($apps as &$a) {
        $a['icon'] = normalizeIcon($a['icon'] ?? '', $a['name'] ?? '', $a['url'] ?? '');
    }
    return $apps;
}
