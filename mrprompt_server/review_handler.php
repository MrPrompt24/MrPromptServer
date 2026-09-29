<?php
// Backend "Panelu przeglądu zmian" w Edytorze: git diff/revert/commit, porównanie z kopią zapasową,
// wykrywanie zmian plików na dysku, szybkie polecenia i paczka kontekstu dla AI.
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/proc_lib.php';
mrp_guard();

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(90);

const RV_SKIP_DIRS = ['node_modules', 'vendor', '.git', '.idea', '.vscode', '__pycache__', 'dist', 'build', '.next', 'cache'];
const RV_MAX_FILE = 1500000;   // maks. rozmiar pliku w diffie
const RV_CTX_MAX_FILE = 200000; // maks. rozmiar pojedynczego pliku w paczce kontekstu

function out($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
function fail($msg)
{
    out(['status' => 'error', 'msg' => $msg]);
}

function dirParam($key = 'path')
{
    $p = realpath($_POST[$key] ?? '');
    if (!$p || !is_dir($p)) fail('Nieprawidłowy folder projektu.');
    return $p;
}

// Zwraca ścieżkę pliku wewnątrz $root (blokuje wyjście poza projekt przez ../)
function insideFile($root, $rel)
{
    $rel = str_replace('\\', '/', (string)$rel);
    if ($rel === '' || strpos($rel, "\0") !== false) fail('Nieprawidłowa ścieżka pliku.');
    foreach (explode('/', $rel) as $seg) if ($seg === '..') fail('Nieprawidłowa ścieżka pliku.');
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
}

function readText($file)
{
    if (!is_file($file)) return ['', false];
    if (filesize($file) > RV_MAX_FILE) return [null, 'too_big'];
    $c = file_get_contents($file);
    if ($c !== false && strpos(substr($c, 0, 8000), "\0") !== false) return [null, 'binary'];
    return [$c === false ? '' : $c, false];
}

function repoTop($path)
{
    [$c, $o] = git(['rev-parse', '--show-toplevel'], $path, 8);
    if ($c !== 0) return null;
    $top = realpath(trim($o));
    return $top ?: null;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    /* ---------- GIT ---------- */
    case 'git_status': {
        $path = dirParam();
        $top = repoTop($path);
        if (!$top) out(['status' => 'ok', 'repo' => false]);
        [, $b] = git(['rev-parse', '--abbrev-ref', 'HEAD'], $top, 8);
        $light = !empty($_POST['light']);
        [$c, $o] = git(['status', '--porcelain=v1', '-z', $light ? '-unormal' : '-uall'], $top, 25);
        if ($c !== 0) fail('git status nie powiódł się.');
        $files = [];
        $parts = explode("\0", $o);
        for ($i = 0; $i < count($parts); $i++) {
            $e = $parts[$i];
            if (strlen($e) < 4) continue;
            $x = $e[0];
            $y = $e[1];
            $file = substr($e, 3);
            if ($x === 'R' || $x === 'C') $i++; // następny wpis to stara nazwa
            $code = $x === '?' ? '?' : ($x === 'A' || $y === 'A' ? 'A' : ($x === 'D' || $y === 'D' ? 'D' : ($x === 'R' ? 'R' : 'M')));
            if ($x === 'U' || $y === 'U') $code = 'U';
            $files[] = ['file' => $file, 'status' => $code, 'staged' => $x !== ' ' && $x !== '?'];
            if (count($files) >= 800) break;
        }
        $stat = '';
        if (!$light) [, $stat] = git(['diff', '--shortstat', 'HEAD'], $top, 15);
        out(['status' => 'ok', 'repo' => true, 'root' => $top, 'branch' => trim($b), 'files' => $files, 'truncated' => count($files) >= 800, 'shortstat' => trim($stat)]);
    }

    case 'git_init': {
        $path = dirParam();
        if (repoTop($path) === $path) fail('Repozytorium już istnieje.');
        [$c, , $e] = git(['init'], $path, 15);
        if ($c !== 0) fail('git init: ' . trim($e));
        if (!is_file($path . '/.gitignore')) {
            file_put_contents($path . '/.gitignore', "node_modules/\nvendor/\n.env\n*.log\n");
        }
        git(['add', '-A'], $path, 60);
        $env = ['GIT_AUTHOR_NAME' => 'MrPrompt', 'GIT_AUTHOR_EMAIL' => 'local@mrprompt', 'GIT_COMMITTER_NAME' => 'MrPrompt', 'GIT_COMMITTER_EMAIL' => 'local@mrprompt'];
        [$n] = git(['config', 'user.name'], $path, 8);
        if ($n === 0) $env = [];
        [$c, $o, $e] = git(['commit', '-m', 'Stan początkowy (MrPrompt Server)', '--allow-empty'], $path, 60, $env);
        $c === 0 ? out(['status' => 'ok']) : fail('git commit: ' . trim($e ?: $o));
    }

    case 'git_diff': {
        $top = repoTop(dirParam());
        if (!$top) fail('To nie jest repozytorium git.');
        $rel = $_POST['file'] ?? '';
        $abs = insideFile($top, $rel);
        [$c, $orig] = git(['show', 'HEAD:' . str_replace('\\', '/', $rel)], $top, 15);
        if ($c !== 0) $orig = ''; // plik nowy (nie ma go w HEAD)
        [$mod, $flag] = readText($abs);
        if ($flag) out(['status' => 'ok', 'flag' => $flag]);
        if (strlen($orig) > RV_MAX_FILE) out(['status' => 'ok', 'flag' => 'too_big']);
        if (strpos(substr($orig, 0, 8000), "\0") !== false) out(['status' => 'ok', 'flag' => 'binary']);
        out(['status' => 'ok', 'original' => $orig, 'modified' => $mod, 'exists' => is_file($abs), 'abs' => $abs]);
    }

    case 'git_revert': {
        $top = repoTop(dirParam());
        if (!$top) fail('To nie jest repozytorium git.');
        $rel = str_replace('\\', '/', $_POST['file'] ?? '');
        $abs = insideFile($top, $rel);
        [$inHead] = git(['cat-file', '-e', 'HEAD:' . $rel], $top, 8);
        if ($inHead === 0) {
            [$c, , $e] = git(['checkout', 'HEAD', '--', $rel], $top, 15);
        } else {
            [$tracked] = git(['ls-files', '--error-unmatch', '--', $rel], $top, 8);
            if ($tracked === 0) {
                [$c, , $e] = git(['rm', '-f', '--', $rel], $top, 15);
            } else {
                $c = is_file($abs) && @unlink($abs) ? 0 : 1;
                $e = 'Nie można usunąć pliku';
            }
        }
        $c === 0 ? out(['status' => 'ok']) : fail('Cofanie nie powiodło się: ' . trim($e ?? ''));
    }

    case 'git_commit': {
        $top = repoTop(dirParam());
        if (!$top) fail('To nie jest repozytorium git.');
        $msg = trim($_POST['message'] ?? '');
        if ($msg === '') fail('Podaj opis zatwierdzenia (commit).');
        $files = json_decode($_POST['files'] ?? '[]', true) ?: [];
        if ($files) {
            foreach ($files as $f) insideFile($top, $f);
            [$c, , $e] = git(array_merge(['add', '-A', '--'], $files), $top, 30);
        } else {
            [$c, , $e] = git(['add', '-A'], $top, 30);
        }
        if ($c !== 0) fail('git add: ' . trim($e));
        // gdy git nie ma skonfigurowanej tożsamości – użyj domyślnej lokalnej
        $env = ['GIT_AUTHOR_NAME' => 'MrPrompt', 'GIT_AUTHOR_EMAIL' => 'local@mrprompt', 'GIT_COMMITTER_NAME' => 'MrPrompt', 'GIT_COMMITTER_EMAIL' => 'local@mrprompt'];
        [$n] = git(['config', 'user.name'], $top, 8);
        if ($n === 0) $env = [];
        $args = ['commit', '-m', $msg];
        if ($files) $args = array_merge($args, ['--'], $files);
        [$c, $o, $e] = git($args, $top, 40, $env);
        $c === 0 ? out(['status' => 'ok', 'output' => trim($o)]) : fail('git commit: ' . trim($e ?: $o));
    }

    /* ---------- MIGAWKI (kopie zapasowe) ---------- */
    case 'snap_list': {
        $path = dirParam();
        $root = realpath($_POST['backup_root'] ?? '');
        if (!$root || !is_dir($root)) out(['status' => 'ok', 'snapshots' => []]);
        $name = basename($path);
        $list = [];
        foreach (scandir($root) as $d) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2})_' . preg_quote($name, '/') . '$/u', $d, $m) && is_dir($root . DIRECTORY_SEPARATOR . $d)) {
                $list[] = ['path' => $root . DIRECTORY_SEPARATOR . $d, 'label' => $m[1] . ' ' . str_replace('-', ':', $m[2])];
            }
        }
        usort($list, fn($a, $b) => strcmp($b['label'], $a['label']));
        out(['status' => 'ok', 'snapshots' => array_slice($list, 0, 30)]);
    }

    case 'snap_status': {
        $path = dirParam();
        $base = dirParam('base');
        $scan = function ($root) {
            $files = [];
            $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                fn($cur) => !($cur->isDir() && in_array($cur->getFilename(), RV_SKIP_DIRS, true))
            ));
            foreach ($it as $f) {
                $files[str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1))] = [$f->getSize(), $f->getPathname()];
                if (count($files) > 5000) break;
            }
            return $files;
        };
        $cur = $scan($path);
        $old = $scan($base);
        $res = [];
        foreach ($cur as $rel => [$sz, $p]) {
            if (!isset($old[$rel])) {
                $res[] = ['file' => $rel, 'status' => 'A'];
            } elseif ($old[$rel][0] !== $sz || ($sz < 1000000 && md5_file($p) !== md5_file($old[$rel][1]))) {
                $res[] = ['file' => $rel, 'status' => 'M'];
            }
        }
        foreach ($old as $rel => $_) if (!isset($cur[$rel])) $res[] = ['file' => $rel, 'status' => 'D'];
        usort($res, fn($a, $b) => strcmp($a['file'], $b['file']));
        out(['status' => 'ok', 'files' => array_slice($res, 0, 800), 'truncated' => count($res) > 800]);
    }

    case 'snap_diff': {
        $path = dirParam();
        $base = dirParam('base');
        $rel = $_POST['file'] ?? '';
        [$orig, $f1] = readText(insideFile($base, $rel));
        [$mod, $f2] = readText(insideFile($path, $rel));
        if ($f1 || $f2) out(['status' => 'ok', 'flag' => $f1 ?: $f2]);
        out(['status' => 'ok', 'original' => $orig, 'modified' => $mod, 'exists' => is_file(insideFile($path, $rel)), 'abs' => insideFile($path, $rel)]);
    }

    case 'snap_restore': {
        $path = dirParam();
        $base = dirParam('base');
        $rel = $_POST['file'] ?? '';
        $src = insideFile($base, $rel);
        $dst = insideFile($path, $rel);
        if (is_file($src)) {
            @mkdir(dirname($dst), 0777, true);
            copy($src, $dst) ? out(['status' => 'ok']) : fail('Nie udało się przywrócić pliku.');
        }
        is_file($dst) && @unlink($dst) ? out(['status' => 'ok']) : fail('Nie udało się usunąć pliku.');
    }

    /* ---------- Zmiany plików na dysku (auto-odświeżanie kart) ---------- */
    case 'stat': {
        $paths = json_decode($_POST['paths'] ?? '[]', true) ?: [];
        $res = [];
        clearstatcache();
        foreach (array_slice($paths, 0, 60) as $p) {
            $res[$p] = is_file($p) ? ['m' => filemtime($p), 's' => filesize($p)] : ['gone' => true];
        }
        out(['status' => 'ok', 'files' => $res]);
    }

    /* ---------- Szybkie polecenia ---------- */
    case 'quick': {
        $key = $_POST['key'] ?? '';
        $php = phpBin();
        $dir = isset($_POST['path']) && $_POST['path'] !== '' ? dirParam() : null;
        switch ($key) {
            case 'php_lint': {
                $file = realpath($_POST['file'] ?? '');
                if (!$file || !is_file($file)) fail('Zapisz plik na dysku, aby sprawdzić składnię.');
                [$c, $o, $e] = run([$php, '-l', $file], null, 20);
                out(['status' => 'ok', 'title' => 'Składnia PHP: ' . basename($file), 'ok' => $c === 0, 'output' => trim($o . $e)]);
            }
            case 'php_lint_all': {
                if (!$dir) fail('Brak folderu projektu.');
                $bad = [];
                $n = 0;
                $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    fn($cur) => !($cur->isDir() && in_array($cur->getFilename(), RV_SKIP_DIRS, true))
                ));
                $deadline = microtime(true) + 60;
                foreach ($it as $f) {
                    if (strtolower($f->getExtension()) !== 'php') continue;
                    if (++$n > 400 || microtime(true) > $deadline) break;
                    [$c, $o, $e] = run([$php, '-l', $f->getPathname()], null, 15);
                    if ($c !== 0) $bad[] = trim($o . $e);
                }
                out(['status' => 'ok', 'title' => 'Składnia PHP w projekcie', 'ok' => !$bad,
                    'output' => "Sprawdzono plików: $n\n" . ($bad ? "Błędy (" . count($bad) . "):\n\n" . implode("\n\n", $bad) : 'Brak błędów składni.')]);
            }
            case 'git_status': case 'git_log': {
                if (!$dir) fail('Brak folderu projektu.');
                $args = $key === 'git_status' ? ['status', '-sb'] : ['log', '--oneline', '--decorate', '-n', '20'];
                [$c, $o, $e] = git($args, $dir, 20);
                out(['status' => 'ok', 'title' => $key === 'git_status' ? 'git status' : 'git log', 'ok' => $c === 0, 'output' => trim($o . $e) ?: '(pusto)']);
            }
            case 'npm_test': {
                if (!$dir || !is_file($dir . '/package.json')) fail('Brak package.json w tym folderze.');
                [$c, $o, $e] = run(['cmd', '/c', 'npm', 'test', '--silent'], $dir, 90);
                out(['status' => 'ok', 'title' => 'npm test', 'ok' => $c === 0, 'output' => trim($o . $e) ?: '(brak wyjścia)']);
            }
        }
        fail('Nieznane polecenie.');
    }

    /* ---------- Paczka kontekstu dla AI ---------- */
    case 'ctx_list': {
        $path = dirParam();
        $files = [];
        [$c, $o] = git(['ls-files', '-z', '-co', '--exclude-standard'], $path, 25);
        if ($c === 0 && repoTop($path)) {
            $files = array_filter(explode("\0", $o));
        } else {
            $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                fn($cur) => !($cur->isDir() && in_array($cur->getFilename(), RV_SKIP_DIRS, true))
            ));
            foreach ($it as $f) {
                $files[] = str_replace('\\', '/', substr($f->getPathname(), strlen($path) + 1));
                if (count($files) > 3000) break;
            }
        }
        $binExt = 'png jpg jpeg gif webp ico bmp svgz pdf zip rar 7z gz tar exe dll so bin mp3 mp4 mov avi woff woff2 ttf otf eot sqlite sqlite3 db lock map psd';
        $bin = array_flip(explode(' ', $binExt));
        $res = [];
        foreach ($files as $rel) {
            $abs = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($abs)) continue;
            $base = strtolower(basename($rel));
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            $secret = (bool)preg_match('/^\.env|\.pem$|\.key$|\.p12$|\.pfx$|^id_(rsa|dsa|ecdsa|ed25519)|secret|credentials|\.htpasswd$/', $base);
            $size = filesize($abs);
            $skip = $secret ? 'secret' : (isset($bin[$ext]) ? 'binary' : ($size > RV_CTX_MAX_FILE ? 'big' : ''));
            $res[] = ['file' => $rel, 'size' => $size, 'skip' => $skip];
            if (count($res) >= 2500) break;
        }
        usort($res, fn($a, $b) => strcmp($a['file'], $b['file']));
        out(['status' => 'ok', 'files' => $res, 'name' => basename($path)]);
    }

    case 'ctx_pack': {
        $path = dirParam();
        $sel = json_decode($_POST['files'] ?? '[]', true) ?: [];
        $redacted = 0;
        $body = '';
        $tree = [];
        $used = 0;
        foreach ($sel as $rel) {
            $abs = insideFile($path, $rel);
            if (!is_file($abs) || filesize($abs) > RV_CTX_MAX_FILE) continue;
            $c = file_get_contents($abs);
            if ($c === false || strpos(substr($c, 0, 8000), "\0") !== false) continue;
            // maskowanie oczywistych sekretów w wartościach
            $c = preg_replace_callback('/((?:api[_-]?key|secret|passw(?:or)?d|pass|token|auth|private[_-]?key)[\'"]?\s*(?:=>|=|:)\s*[\'"])([^\'"\r\n]{6,})([\'"])/i', function ($m) use (&$redacted) {
                $redacted++;
                return $m[1] . '***REDACTED***' . $m[3];
            }, $c);
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            $fence = strpos($c, '```') !== false ? '````' : '```';
            $body .= "\n## " . $rel . "\n" . $fence . $ext . "\n" . rtrim($c) . "\n" . $fence . "\n";
            $tree[] = $rel;
            $used++;
        }
        $text = '# Projekt: ' . basename($path) . "\n\nPliki ($used):\n" . implode("\n", array_map(fn($t) => '- ' . $t, $tree)) . "\n" . $body;
        out(['status' => 'ok', 'text' => $text, 'files' => $used, 'chars' => mb_strlen($text), 'redacted' => $redacted]);
    }

    /* ---------- Pliki sterujące AI ---------- */
    case 'ai_files': {
        $path = dirParam();
        $cands = ['CLAUDE.md', 'AGENTS.md', 'GEMINI.md', '.cursorrules', '.github/copilot-instructions.md', 'README.md', 'TODO.md'];
        $found = [];
        foreach ($cands as $c) if (is_file($path . DIRECTORY_SEPARATOR . $c)) $found[] = ['file' => $c, 'abs' => realpath($path . DIRECTORY_SEPARATOR . $c)];
        foreach (['.claude', '.cursor/rules', 'prompts', 'docs'] as $d) {
            $dd = $path . DIRECTORY_SEPARATOR . $d;
            if (!is_dir($dd)) continue;
            foreach (array_slice(scandir($dd), 0, 60) as $f) {
                if ($f[0] === '.' && $d !== '.claude') continue;
                if (is_file($dd . DIRECTORY_SEPARATOR . $f) && preg_match('/\.(md|mdc|txt|json)$/i', $f)) {
                    $found[] = ['file' => $d . '/' . $f, 'abs' => realpath($dd . DIRECTORY_SEPARATOR . $f)];
                }
            }
        }
        out(['status' => 'ok', 'files' => $found, 'root' => $path]);
    }
}

fail('Nieznana akcja.');
