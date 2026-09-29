<?php
// Operacje na plikach i folderach z menu pod prawym przyciskiem (panel główny) i z Edytora.
//  - create_file / create_folder: w dowolnym istniejącym folderze (nigdy nie nadpisują istniejących)
//  - rename / delete / reveal / info: tylko wewnątrz DOCUMENT_ROOT (htdocs)
require_once __DIR__ . '/db_init.php';
require_once __DIR__ . '/security.php';
mrp_guard();

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

function out($d)
{
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail($m)
{
    out(['status' => 'error', 'msg' => $m]);
}

// Nazwa pojedynczego pliku/folderu: bez ścieżek, bez znaków zabronionych w Windows
function cleanName($n)
{
    $n = trim((string)$n);
    if ($n === '' || $n === '.' || $n === '..') fail('Podaj poprawną nazwę.');
    if (mb_strlen($n) > 200) fail('Nazwa jest zbyt długa.');
    if (preg_match('/[<>:"\/\\\\|?*\x00-\x1f]/u', $n)) fail('Nazwa zawiera niedozwolone znaki: < > : " / \\ | ? *');
    if (preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(\..*)?$/i', $n)) fail('Ta nazwa jest zarezerwowana w Windows.');
    if (substr($n, -1) === '.' || substr($n, -1) === ' ') fail('Nazwa nie może kończyć się kropką ani spacją.');
    return $n;
}

$docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
function insideRoot($path, $docRoot)
{
    $p = realpath($path);
    if (!$p || !$docRoot) return null;
    return (strcasecmp($p, $docRoot) !== 0 && stripos($p, $docRoot . DIRECTORY_SEPARATOR) === 0) ? $p : null;
}

function rrmdir($dir)
{
    foreach (@scandir($dir) ?: [] as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($p) && !is_link($p)) {
            if (!rrmdir($p)) return false;
        } else {
            @chmod($p, 0666);
            if (!@unlink($p)) return false;
        }
    }
    @chmod($dir, 0777);
    return @rmdir($dir);
}

$action = $_POST['action'] ?? '';
$path = $_POST['path'] ?? '';

switch ($action) {

    case 'create_file':
    case 'create_folder': {
        $parent = realpath($path);
        if (!$parent || !is_dir($parent)) fail('Folder docelowy nie istnieje.');
        $name = cleanName($_POST['name'] ?? '');
        $target = $parent . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target)) fail('„' . $name . '” już istnieje w tym miejscu.');
        if ($action === 'create_folder') {
            @mkdir($target, 0777) ? out(['status' => 'ok', 'path' => $target]) : fail('Nie udało się utworzyć folderu.');
        }
        file_put_contents($target, '') !== false ? out(['status' => 'ok', 'path' => $target]) : fail('Nie udało się utworzyć pliku.');
    }

    case 'rename': {
        $old = insideRoot($path, $docRoot);
        if (!$old) fail('Można zmieniać tylko elementy w folderze htdocs.');
        $name = cleanName($_POST['name'] ?? '');
        $new = dirname($old) . DIRECTORY_SEPARATOR . $name;
        if (strcasecmp($new, $old) === 0 && $new === $old) out(['status' => 'ok', 'path' => $old]);
        if (file_exists($new) && strcasecmp($new, $old) !== 0) fail('„' . $name . '” już istnieje w tym miejscu.');
        if (!@rename($old, $new)) fail('Nie udało się zmienić nazwy (element może być używany przez inny program).');
        // ulubione: zaktualizuj ścieżkę zmienionego folderu i jego podfolderów
        try {
            $norm = fn($p) => strtolower(str_replace('\\', '/', $p));
            $o = $norm($old);
            $upd = $db->prepare('UPDATE favorites SET path = ? WHERE path = ?');
            foreach ($db->query('SELECT path FROM favorites')->fetchAll(PDO::FETCH_COLUMN) as $fp) {
                $n = $norm($fp);
                if ($n === $o || strpos($n, $o . '/') === 0) {
                    $rest = substr(str_replace('\\', '/', $fp), strlen($o));
                    $newFav = str_replace('/', '\\', str_replace('\\', '/', $new)) . str_replace('/', '\\', $rest);
                    $upd->execute([$newFav, $fp]);
                }
            }
        } catch (Throwable $e) {
        }
        out(['status' => 'ok', 'path' => $new]);
    }

    case 'delete': {
        $p = insideRoot($path, $docRoot);
        if (!$p) fail('Można usuwać tylko elementy w folderze htdocs.');
        // nie usuwaj samej aplikacji, z której korzystasz
        $self = realpath(dirname(__DIR__));
        if ($self && (strcasecmp($p, $self) === 0 || stripos($self, $p . DIRECTORY_SEPARATOR) === 0)) {
            fail('Tego folderu nie można usunąć: zawiera działającą aplikację MrPrompt Server.');
        }
        $ok = is_dir($p) ? rrmdir($p) : (@chmod($p, 0666) | true) && @unlink($p);
        if (!$ok) fail('Nie udało się usunąć (element może być używany przez inny program).');
        try {
            $db->prepare('DELETE FROM favorites WHERE lower(replace(path, char(92), "/")) = ? OR lower(replace(path, char(92), "/")) LIKE ?')
                ->execute([strtolower(str_replace('\\', '/', $p)), strtolower(str_replace('\\', '/', $p)) . '/%']);
        } catch (Throwable $e) {
        }
        out(['status' => 'ok']);
    }

    case 'reveal': {
        $p = insideRoot($path, $docRoot);
        if (!$p) fail('Można otwierać tylko elementy w folderze htdocs.');
        if (strpos($p, '"') !== false || strpos($p, '%') !== false || strpos($p, '&') !== false || strpos($p, '^') !== false) {
            fail('Ścieżka zawiera znaki, których nie można bezpiecznie przekazać do Eksploratora.');
        }
        $cmd = is_dir($p) ? 'start "" explorer "' . $p . '"' : 'start "" explorer /select,"' . $p . '"';
        pclose(popen($cmd, 'r'));
        out(['status' => 'ok']);
    }
}

fail('Nieznana akcja.');
