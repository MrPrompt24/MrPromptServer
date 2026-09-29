<?php
// Wspólny kod drzewa projektów: index.php (poziom główny) i tree_handler.php (leniwe ładowanie).
require_once __DIR__ . '/project_handler.php'; // getProjectInfo()

// Katalogi pomijane przy skanowaniu (ciężkie, generowane)
const TREE_SKIP = ['node_modules', 'vendor', '.git', '.idea', '.vscode', '__pycache__'];

// Najnowszy czas modyfikacji w całym poddrzewie. mtime samego folderu nie zmienia się
// przy edycji plików głębiej, dlatego filtr "Ostatnie" wcześniej gubił foldery.
function newestMtime($path, $depth = 0)
{
    $newest = (int)@filemtime($path);
    if (!is_dir($path) || $depth > 3) return $newest;
    $items = @scandir($path);
    if (!$items) return $newest;
    foreach ($items as $it) {
        if ($it === '.' || $it === '..' || in_array($it, TREE_SKIP, true)) continue;
        $p = $path . DIRECTORY_SEPARATOR . $it;
        if (is_link($p)) continue; // unikamy pętli przez junctiony
        $m = is_dir($p) ? newestMtime($p, $depth + 1) : (int)@filemtime($p);
        if ($m > $newest) $newest = $m;
    }
    return $newest;
}

// Czas z krótkim cache (kosztowny skan całego htdocs); klucz = ścieżka
function newestMtimeCached($path, $ttl = 90)
{
    static $cache = null;
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mrp_tree_mtime.json';
    if ($cache === null) {
        $cache = is_file($file) ? (json_decode(@file_get_contents($file), true) ?: []) : [];
    }
    $e = $cache[$path] ?? null;
    if ($e && time() - $e[0] < $ttl) return $e[1];
    $m = newestMtime($path);
    $cache[$path] = [time(), $m];
    register_shutdown_function(function () use (&$cache, $file) {
        @file_put_contents($file, json_encode($cache), LOCK_EX);
    });
    return $m;
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Renderuje JEDEN poziom drzewa; podfoldery ładowane są na żądanie (data-lazy)
function renderTree($dir, $favPaths, $top = false)
{
    $items = @scandir($dir);
    if (!$items) return '';
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $html = '<ul>';
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item[0] === '.') continue;
        if (in_array($item, TREE_SKIP, true)) continue;
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($fullPath);
        $mtime = $top ? newestMtimeCached($fullPath) : (int)@filemtime($fullPath);
        $isFav = in_array($fullPath, $favPaths, true);
        $p = h($fullPath);
        $n = h($item);

        $html .= "<li data-mtime='$mtime' data-fav='" . ($isFav ? '1' : '0') . "'>";
        $html .= "<div class='tree-item' data-path='$p' data-type='" . ($isDir ? 'dir' : 'file') . "' data-name='$n'>";
        if ($isDir) {
            $info = getProjectInfo($fullPath);
            $html .= "<span class='folder-toggle'><i class='fa-solid fa-chevron-right'></i></span> <span class='icon folder-icon'><i class='fa-solid fa-folder'></i></span> ";
            $html .= "<div class='item-name-wrap'>";
            $html .= "<strong data-act='open' data-path='$p' data-name='$n'>$n</strong>";
            if ($info) {
                $shortDesc = h($info['short'] ?? '');
                $created = h($info['created'] ?? '-');
                $githubUrl = h($info['github_url'] ?? '');
                $html .= "<span class='info-badge' data-act='open' data-path='$p' data-name='$n' data-tooltip='$shortDesc'>i</span>";
                if ($githubUrl !== '' && preg_match('~^https?://~i', $info['github_url'] ?? '')) {
                    $html .= "<span class='github-icon' data-act='github' data-url='$githubUrl' title='Otwórz repozytorium GitHub'><i class='fa-brands fa-github'></i></span>";
                }
                $html .= "<div class='full-view-columns' data-act='open' data-path='$p' data-name='$n'><span class='full-view-desc' title='$shortDesc'>$shortDesc</span><span class='full-view-date'>$created</span></div>";
            }
            $html .= '</div>';
            $html .= "<span class='fav-star" . ($isFav ? ' active' : '') . "' data-act='fav' data-path='$p'>★</span>";
            $html .= "<span class='trash-icon' data-act='delete' data-path='$p' data-name='$n' title='Usuń folder z dysku'><i class='fa-solid fa-trash'></i></span>";
        } else {
            $rel = str_replace('\\', '/', substr($fullPath, strlen($_SERVER['DOCUMENT_ROOT'])));
            $rel = implode('/', array_map('rawurlencode', explode('/', $rel)));
            $html .= "<span class='icon'><i class='fa-solid fa-file'></i></span> <div class='item-name-wrap'><a href='" . h($rel) . "' target='_blank' rel='noopener'>$n</a></div>";
        }
        $html .= '</div>';
        if ($isDir) $html .= "<div class='nested' data-lazy='$p'></div>";
        $html .= '</li>';
    }
    return $html . '</ul>';
}
