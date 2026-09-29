<?php
// Narzędzia diagnostyczne panelu: logi błędów, kontrola działania projektu, porty i procesy,
// MariaDB (start, zrzuty), audyt przed publikacją i kreator nowego projektu.
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/proc_lib.php';
mrp_guard();

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);

function out($d)
{
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
function fail($m)
{
    out(['status' => 'error', 'msg' => $m]);
}

const T_SKIP_DIRS = ['node_modules', 'vendor', '.git', '.idea', '.vscode', '__pycache__'];

$docRootRaw = $_SERVER['DOCUMENT_ROOT'];
$docRoot = realpath($docRootRaw);
$xamppRoot = realpath(dirname($docRoot)) ?: 'C:\\xampp';
if (!is_dir($xamppRoot . '\\apache')) $xamppRoot = 'C:\\xampp';
$norm = fn($p) => strtolower(str_replace('\\', '/', (string)$p));

function projectDir($rel = null)
{
    global $docRoot;
    $p = realpath($rel ?? ($_POST['path'] ?? ''));
    if (!$p || !is_dir($p) || !$docRoot || stripos($p, $docRoot . DIRECTORY_SEPARATOR) !== 0) fail('Wskaż folder projektu wewnątrz htdocs.');
    return $p;
}
function projectNameOf($dir)
{
    global $docRoot;
    $rel = ltrim(substr($dir, strlen($docRoot)), '\\/');
    return explode(DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $rel))[0];
}

/* ===================== LOGI ===================== */
function tailChunk($file, $maxBytes, $offset = null)
{
    if (!is_file($file)) return ['', 0, 0];
    $size = filesize($file);
    $fh = @fopen($file, 'rb');
    if (!$fh) return ['', $size, 0];
    $start = $offset !== null && $offset <= $size ? $offset : max(0, $size - $maxBytes);
    if ($size - $start > $maxBytes) $start = $size - $maxBytes;
    fseek($fh, $start);
    $data = stream_get_contents($fh);
    fclose($fh);
    if ($start > 0 && ($offset === null || $start !== $offset)) { // odetnij niepełną pierwszą linię
        $nl = strpos($data, "\n");
        if ($nl !== false) $data = substr($data, $nl + 1);
    }
    return [$data, $size, $start];
}

function levelOf($label)
{
    $l = strtolower($label);
    if (strpos($l, 'parse') !== false) return 'parse';
    if (strpos($l, 'fatal') !== false || $l === 'error') return 'fatal';
    if (strpos($l, 'warning') !== false) return 'warning';
    if (strpos($l, 'deprecated') !== false || strpos($l, 'strict') !== false) return 'deprecated';
    if (strpos($l, 'notice') !== false) return 'notice';
    return 'other';
}

function parseLogText($text, $source)
{
    global $docRoot, $norm;
    $entries = [];
    $cur = null;
    $flush = function () use (&$cur, &$entries) {
        if ($cur) $entries[] = $cur;
        $cur = null;
    };
    foreach (preg_split('/\r?\n/', $text) as $line) {
        if ($line === '') continue;
        $time = null;
        $msg = null;
        $lvl = null;
        if ($source === 'php') {
            if (preg_match('/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})[^\]]*\]\s+(.*)$/', $line, $m)) {
                $dt = DateTime::createFromFormat('d-M-Y H:i:s', $m[1]);
                $time = $dt ? $dt->getTimestamp() : null;
                $msg = $m[2];
            }
        } else {
            if (preg_match('/^\[([A-Za-z]{3} [A-Za-z]{3} +\d+ [\d:.]+ \d{4})\] \[([^\]]+)\] (?:\[pid [^\]]*\] )?(?:\[client [^\]]*\] )?(.*)$/', $line, $m)) {
                $t = preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+/', '$1', $m[1]);
                $time = strtotime($t) ?: null;
                $msg = $m[3];
                $lvl = $m[2];
            }
        }
        if ($msg !== null) {
            $flush();
            $level = 'other';
            $text2 = $msg;
            if (preg_match('/^(?:PHP )?(Fatal error|Parse error|Catchable fatal error|Warning|Notice|Deprecated|Strict Standards|Error):\s*(.*)$/s', $msg, $mm)) {
                $level = levelOf($mm[1]);
                $text2 = $mm[2];
            } elseif ($source === 'apache' && $lvl !== null) {
                if (preg_match('/:(error|crit|alert|emerg)\b/', $lvl)) $level = 'fatal';
                elseif (preg_match('/:warn\b/', $lvl)) $level = 'warning';
                else $level = 'notice';
            }
            $file = null;
            $ln = null;
            if (preg_match('/ in ([A-Za-z]:[^\s].*?) on line (\d+)/', $text2, $fm) || preg_match('/ in ([A-Za-z]:[^\s].*?):(\d+)\b/', $text2, $fm)) {
                $file = $fm[1];
                $ln = (int)$fm[2];
            }
            $project = null;
            if ($file && $docRoot && strpos($norm($file), $norm($docRoot) . '/') === 0) {
                $rel = substr($norm($file), strlen($norm($docRoot)) + 1);
                $project = explode('/', $rel)[0];
                // zachowaj oryginalną wielkość liter nazwy projektu
                $relOrig = ltrim(substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $docRoot))), '/');
                $project = explode('/', $relOrig)[0];
            }
            $cur = ['time' => $time, 'level' => $level, 'message' => $text2, 'file' => $file, 'line' => $ln, 'project' => $project, 'detail' => '', 'source' => $source];
        } elseif ($cur) {
            $cur['detail'] .= ($cur['detail'] === '' ? '' : "\n") . $line; // ślad stosu i kontynuacje
            if (!$cur['file'] && preg_match('/thrown in (.+?) on line (\d+)/', $line, $fm)) {
                $cur['file'] = $fm[1];
                $cur['line'] = (int)$fm[2];
            }
        }
    }
    $flush();
    return $entries;
}

function logFiles()
{
    global $xamppRoot;
    $php = ini_get('error_log') ?: ($xamppRoot . '\\php\\logs\\php_error_log');
    return [
        'php' => $php,
        'apache' => $xamppRoot . '\\apache\\logs\\error.log',
    ];
}

function collectLogEntries($sources, $maxBytes = 1500000, $offsets = [])
{
    $files = logFiles();
    $all = [];
    $info = [];
    foreach ($sources as $src) {
        if (!isset($files[$src])) continue;
        $f = $files[$src];
        [$txt, $size, $start] = tailChunk($f, $maxBytes, isset($offsets[$src]) ? (int)$offsets[$src] : null);
        $info[$src] = ['path' => $f, 'size' => $size, 'exists' => is_file($f), 'mtime' => is_file($f) ? filemtime($f) : 0];
        foreach (parseLogText($txt, $src) as $e) $all[] = $e;
    }
    return [$all, $info];
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'log_read': {
        $sources = array_values(array_intersect(explode(',', $_POST['sources'] ?? 'php,apache'), ['php', 'apache']));
        if (!$sources) $sources = ['php'];
        $offsets = json_decode($_POST['offsets'] ?? '{}', true) ?: [];
        $projectFilter = trim($_POST['project'] ?? '');
        $levels = array_filter(explode(',', $_POST['levels'] ?? 'fatal,parse,warning'));
        [$all, $info] = collectLogEntries($sources, 1500000, $offsets);
        // kolejność: najnowsze najpierw
        usort($all, fn($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
        $counts = ['fatal' => 0, 'parse' => 0, 'warning' => 0, 'deprecated' => 0, 'notice' => 0, 'other' => 0];
        $projCounts = [];
        $collapsed = [];
        foreach ($all as $e) {
            if ($projectFilter !== '' && strcasecmp((string)$e['project'], $projectFilter) !== 0) continue;
            if (!in_array($e['level'], $levels, true)) continue;
            $counts[$e['level']]++;
            if ($e['project']) $projCounts[$e['project']] = ($projCounts[$e['project']] ?? 0) + 1;
            $key = $e['level'] . '|' . $e['message'] . '|' . $e['file'] . '|' . $e['line'];
            if (isset($collapsed[$key])) {
                $collapsed[$key]['count']++;
                $collapsed[$key]['first_time'] = min($collapsed[$key]['first_time'] ?? PHP_INT_MAX, $e['time'] ?? PHP_INT_MAX);
                continue;
            }
            $e['count'] = 1;
            $e['first_time'] = $e['time'];
            $e['detail'] = mb_substr($e['detail'], 0, 3000);
            $e['message'] = mb_substr($e['message'], 0, 1500);
            $collapsed[$key] = $e;
        }
        arsort($projCounts);
        $entries = array_slice(array_values($collapsed), 0, 300);
        out(['status' => 'ok', 'entries' => $entries, 'counts' => $counts, 'projects' => $projCounts, 'files' => $info, 'now' => time()]);
    }

    case 'log_context': {
        $file = realpath($_POST['file'] ?? '');
        $line = max(1, (int)($_POST['line'] ?? 1));
        if (!$file || !is_file($file) || stripos($file, $docRoot . DIRECTORY_SEPARATOR) !== 0) fail('Plik jest poza htdocs albo nie istnieje.');
        if (filesize($file) > 2000000) fail('Plik jest zbyt duży.');
        $lines = preg_split('/\r?\n/', file_get_contents($file));
        $r = 6;
        $from = max(1, $line - $r);
        $to = min(count($lines), $line + $r);
        $snip = [];
        for ($i = $from; $i <= $to; $i++) $snip[] = ['n' => $i, 't' => $lines[$i - 1], 'hit' => $i === $line];
        out(['status' => 'ok', 'file' => $file, 'lines' => $snip]);
    }

    /* ===================== CZY PROJEKT DZIAŁA? ===================== */
    case 'health': {
        $dir = projectDir();
        $name = projectNameOf($dir);
        $checks = [];
        $add = function ($title, $status, $detail = '', $extra = []) use (&$checks) {
            $checks[] = array_merge(['title' => $title, 'status' => $status, 'detail' => $detail], $extra);
        };

        // 1) plik startowy
        $entry = null;
        foreach (['index.php', 'index.html', 'index.htm'] as $f) if (is_file($dir . DIRECTORY_SEPARATOR . $f)) { $entry = $f; break; }
        $add('Plik startowy', $entry ? 'ok' : 'warn', $entry ? "Znaleziono $entry" : 'Brak index.php / index.html: adres projektu może pokazać listę plików lub błąd 403.');

        // 2) składnia PHP
        $php = phpBin();
        $bad = [];
        $n = 0;
        $deadline = microtime(true) + 45;
        $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            fn($cur) => !($cur->isDir() && in_array($cur->getFilename(), T_SKIP_DIRS, true))
        ));
        foreach ($it as $f) {
            if (strtolower($f->getExtension()) !== 'php') continue;
            if (++$n > 400 || microtime(true) > $deadline) break;
            [$c, $o, $e] = run([$php, '-l', $f->getPathname()], null, 15);
            if ($c !== 0) $bad[] = trim(preg_replace('/^(PHP )?(Parse|Fatal) error:\s*/m', '', $o . $e));
        }
        if ($n === 0) $add('Składnia PHP', 'ok', 'Brak plików PHP w projekcie.');
        elseif ($bad) $add('Składnia PHP', 'fail', 'Błędy składni w ' . count($bad) . ' plikach.', ['items' => array_slice($bad, 0, 8)]);
        else $add('Składnia PHP', 'ok', "Sprawdzono plików: $n. Brak błędów.");

        // 3) odpowiedź HTTP strony głównej projektu
        $port = $_SERVER['SERVER_PORT'] ?? 80;
        $base = 'http://127.0.0.1' . ($port != 80 ? ':' . $port : '');
        $segs = array_map('rawurlencode', explode('/', str_replace('\\', '/', $name)));
        $url = $base . '/' . implode('/', $segs) . '/';
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 3, 'header' => "Host: localhost\r\nUser-Agent: MrPromptHealth/1.0\r\nConnection: close\r\n"]]);
        $t0 = microtime(true);
        $body = @file_get_contents($url, false, $ctx, 0, 400000);
        $ms = round((microtime(true) - $t0) * 1000);
        $status = 0;
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1]; // ostatni po przekierowaniach
        }
        if ($body === false && !$status) {
            $add('Odpowiedź strony', 'fail', 'Brak odpowiedzi w 8 s (strona się zawiesiła lub Apache nie działa).');
            $body = '';
        } else {
            $st = $status >= 500 ? 'fail' : ($status === 404 ? 'fail' : ($status >= 400 ? 'warn' : 'ok'));
            $d = "HTTP $status w {$ms} ms";
            if ($status === 404) $d .= ': brak strony startowej pod adresem projektu.';
            if ($status >= 500) $d .= ': błąd serwera (zajrzyj do logu błędów).';
            if ($ms > 3000) { $d .= ' (wolno)'; if ($st === 'ok') $st = 'warn'; }
            $add('Odpowiedź strony', $st, $d, ['url' => 'http://localhost/' . implode('/', $segs) . '/']);
        }
        // błędy PHP wyświetlone na stronie i biała strona
        if ($body !== '' || $status === 200) {
            $trim = trim(strip_tags((string)$body));
            if ($status === 200 && $trim === '' && !preg_match('/<(img|canvas|video|iframe|svg)\b/i', (string)$body)) {
                $add('Zawartość strony', 'fail', 'Strona jest pusta (tzw. biała strona), to zwykle błąd PHP: sprawdź log błędów.');
            } elseif (preg_match('/(<b>)?(Fatal error|Parse error|Warning|Notice|Deprecated)(<\/b>)?:\s+(.{0,240})/i', (string)$body, $m) || preg_match('/Uncaught\s+\w+|Stack trace:|SQLSTATE\[/', (string)$body, $m)) {
                $add('Zawartość strony', 'fail', 'Na stronie widać komunikat błędu PHP: ' . trim(html_entity_decode(strip_tags($m[0]), ENT_QUOTES, 'UTF-8')));
            } else {
                $title = preg_match('/<title[^>]*>(.*?)<\/title>/is', (string)$body, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : '';
                $add('Zawartość strony', 'ok', $title !== '' ? 'Tytuł strony: ' . mb_substr($title, 0, 100) : 'Strona się wyświetla.');
            }
        }

        // 4) zasoby (CSS/JS/obrazy): 404 i ścieżki od korzenia serwera
        if ($body) {
            preg_match_all('/(?:src|href)\s*=\s*["\']([^"\'#?][^"\']*)["\']/i', $body, $mm);
            $urls = array_unique($mm[1]);
            $checked = 0;
            $missing = [];
            $rootRel = [];
            foreach ($urls as $u) {
                if (preg_match('#^(https?:)?//|^(mailto|tel|javascript|data):#i', $u)) continue;
                if (!preg_match('#\.(css|js|mjs|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|json|php|html?)(\?|$)#i', $u)) continue;
                if (++$checked > 30) break;
                $isRoot = $u[0] === '/';
                $target = $isRoot ? $base . $u : $url . ltrim($u, './');
                $c2 = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true, 'method' => 'HEAD', 'header' => "Host: localhost\r\nConnection: close\r\n"]]);
                @file_get_contents($target, false, $c2, 0, 1);
                $code = 0;
                foreach (($http_response_header ?? []) as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m3)) $code = (int)$m3[1];
                if ($code === 404) {
                    if ($isRoot) $rootRel[] = $u; else $missing[] = $u;
                }
            }
            if ($rootRel) $add('Ścieżki od korzenia serwera', 'fail', 'Te adresy zaczynają się od „/” i szukają pliku poza projektem (w XAMPP projekt leży w podfolderze). Użyj ścieżek względnych, np. „assets/app.css” zamiast „/assets/app.css”.', ['items' => array_slice($rootRel, 0, 10)]);
            if ($missing) $add('Brakujące pliki (404)', 'fail', 'Strona odwołuje się do plików, których nie ma.', ['items' => array_slice($missing, 0, 10)]);
            if (!$rootRel && !$missing) $add('Pliki CSS/JS/obrazy', 'ok', $checked ? "Sprawdzono odwołań: $checked. Wszystkie istnieją." : 'Strona nie ładuje osobnych plików.');
        }

        // 5) świeże błędy w logach
        [$all] = collectLogEntries(['php', 'apache'], 600000);
        $recent = [];
        $cut = time() - 1800;
        foreach ($all as $e) {
            if (strcasecmp((string)$e['project'], $name) === 0 && ($e['time'] ?? 0) >= $cut && in_array($e['level'], ['fatal', 'parse', 'warning'], true)) $recent[] = $e;
        }
        usort($recent, fn($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
        if ($recent) {
            $items = [];
            $seen = [];
            foreach ($recent as $e) {
                $k = $e['message'] . $e['line'];
                if (isset($seen[$k])) continue;
                $seen[$k] = 1;
                $items[] = strtoupper($e['level']) . ': ' . mb_substr($e['message'], 0, 200) . ($e['file'] ? ' [' . basename($e['file']) . ':' . $e['line'] . ']' : '');
                if (count($items) >= 6) break;
            }
            $hasFatal = count(array_filter($recent, fn($e) => $e['level'] !== 'warning')) > 0;
            $add('Błędy w logach (ostatnie 30 min)', $hasFatal ? 'fail' : 'warn', count($recent) . ' wpisów dotyczących tego projektu.', ['items' => $items]);
        } else {
            $add('Błędy w logach (ostatnie 30 min)', 'ok', 'Brak nowych błędów tego projektu.');
        }

        $worst = 'ok';
        foreach ($checks as $c) { if ($c['status'] === 'fail') { $worst = 'fail'; break; } if ($c['status'] === 'warn') $worst = 'warn'; }
        out(['status' => 'ok', 'name' => $name, 'overall' => $worst, 'checks' => $checks, 'url' => 'http://localhost/' . implode('/', $segs) . '/']);
    }

    /* ===================== PORTY I PROCESY ===================== */
    case 'ports_list': {
        [$c, $o] = run(['netstat', '-ano', '-p', 'TCP'], null, 15);
        $listen = [];
        foreach (preg_split('/\r?\n/', $o) as $ln) {
            if (preg_match('/^\s*TCP\s+(\[[^\]]+\]|[\d.]+):(\d+)\s+\S+\s+LISTENING\s+(\d+)/i', $ln, $m)) {
                $pid = (int)$m[3];
                $addr = $m[1];
                $listen[$pid]['ports'][(int)$m[2]] = ['port' => (int)$m[2], 'addr' => $addr, 'public' => !preg_match('/^(127\.|\[::1\])/', $addr)];
            }
        }
        $pids = array_keys($listen);
        $proc = [];
        if ($pids) {
            $ids = implode(',', array_map('intval', $pids));
            $ps = '$ids=@(' . $ids . '); Get-CimInstance Win32_Process | Where-Object { $ids -contains $_.ProcessId } | ForEach-Object { "$($_.ProcessId)|$($_.Name)|$($_.ExecutablePath)|$($_.CommandLine)" }';
            [, $po] = run(['powershell', '-NoProfile', '-NonInteractive', '-Command', $ps], null, 25);
            foreach (preg_split('/\r?\n/', $po) as $ln) {
                $p = explode('|', $ln, 4);
                if (count($p) === 4 && ctype_digit($p[0])) $proc[(int)$p[0]] = ['name' => $p[1], 'exe' => $p[2], 'cmd' => $p[3]];
            }
        }
        $me = getmypid();
        $rows = [];
        foreach ($listen as $pid => $d) {
            $pr = $proc[$pid] ?? ['name' => '', 'exe' => '', 'cmd' => ''];
            $n = strtolower($pr['name']);
            $label = $pr['name'] ?: 'PID ' . $pid;
            $kind = 'inne';
            $killable = true;
            $why = '';
            if ($n === 'httpd.exe') { $kind = 'xampp'; $label = 'Apache (serwer stron)'; $killable = false; $why = 'To serwer, na którym działa ten panel. Zatrzymaj go w panelu XAMPP.'; }
            elseif ($n === 'mysqld.exe' || $n === 'mariadbd.exe') { $kind = 'xampp'; $label = 'MariaDB / MySQL'; $killable = false; $why = 'Baza danych XAMPP. Zatrzymaj ją w panelu XAMPP.'; }
            elseif (in_array($n, ['system', 'svchost.exe', 'services.exe', 'lsass.exe', 'wininit.exe', 'csrss.exe', 'smss.exe', 'winlogon.exe', 'explorer.exe', 'spoolsv.exe', 'dwm.exe'], true) || $pid <= 4) { $kind = 'system'; $killable = false; $why = 'Proces systemu Windows.'; }
            elseif (in_array($n, ['node.exe', 'php.exe', 'python.exe', 'python3.exe', 'deno.exe', 'bun.exe', 'ruby.exe', 'java.exe', 'dotnet.exe'], true)) $kind = 'dev';
            if ($pid === $me) { $killable = false; $why = 'Bieżący proces panelu.'; }
            // projekt na podstawie ścieżki w poleceniu
            $project = null;
            $hay = str_replace('\\', '/', $pr['cmd'] . ' ' . $pr['exe']);
            if ($docRoot && preg_match('#' . preg_quote(str_replace('\\', '/', $docRoot), '#') . '/([^/\s"]+)#i', $hay, $pm)) $project = $pm[1];
            ksort($d['ports']);
            $rows[] = ['pid' => $pid, 'name' => $pr['name'], 'label' => $label, 'kind' => $kind, 'ports' => array_values($d['ports']), 'exe' => $pr['exe'], 'cmd' => mb_substr($pr['cmd'], 0, 300), 'project' => $project, 'killable' => $killable, 'why' => $why];
        }
        usort($rows, fn($a, $b) => [['dev' => 0, 'inne' => 1, 'xampp' => 2, 'system' => 3][$a['kind']], $a['ports'][0]['port'] ?? 0] <=> [['dev' => 0, 'inne' => 1, 'xampp' => 2, 'system' => 3][$b['kind']], $b['ports'][0]['port'] ?? 0]);
        out(['status' => 'ok', 'rows' => $rows]);
    }

    case 'ports_kill': {
        $pid = (int)($_POST['pid'] ?? 0);
        if ($pid <= 4) fail('Nieprawidłowy proces.');
        // pozwól tylko na procesy, które faktycznie nasłuchują na porcie i nie są chronione
        [$c, $o] = run(['netstat', '-ano', '-p', 'TCP'], null, 15);
        if (!preg_match('/LISTENING\s+' . $pid . '\s*$/m', $o)) fail('Ten proces nie nasłuchuje na żadnym porcie.');
        [, $tl] = run(['tasklist', '/FI', 'PID eq ' . $pid, '/FO', 'CSV', '/NH'], null, 10);
        $name = strtolower(trim(explode('","', trim($tl, "\" \r\n"))[0] ?? '', '"'));
        $protected = ['httpd.exe', 'mysqld.exe', 'mariadbd.exe', 'system', 'svchost.exe', 'services.exe', 'lsass.exe', 'wininit.exe', 'csrss.exe', 'smss.exe', 'winlogon.exe', 'explorer.exe', 'spoolsv.exe', 'dwm.exe'];
        if ($pid === getmypid() || in_array($name, $protected, true)) fail('Tego procesu nie można zakończyć z panelu.');
        [$c, $o2, $e] = run(['taskkill', '/PID', (string)$pid, '/T', '/F'], null, 15);
        $c === 0 ? out(['status' => 'ok', 'msg' => trim($o2)]) : fail('Nie udało się zakończyć procesu: ' . trim($o2 . $e));
    }

    /* ===================== MARIADB ===================== */
    case 'mysql_start': {
        $exe = $xamppRoot . '\\mysql\\bin\\mysqld.exe';
        $ini = $xamppRoot . '\\mysql\\bin\\my.ini';
        if (!is_file($exe)) fail('Nie znaleziono mysqld.exe w ' . $xamppRoot . '\\mysql\\bin');
        $up = function () { $fp = @fsockopen('127.0.0.1', 3306, $e, $s, 0.3); if ($fp) { fclose($fp); return true; } return false; };
        if ($up()) out(['status' => 'ok', 'msg' => 'MariaDB już działa.', 'running' => true]);
        $cmd = 'start "" /B "' . $exe . '" --defaults-file="' . $ini . '" --standalone --console';
        pclose(popen($cmd, 'r'));
        for ($i = 0; $i < 25; $i++) { usleep(400000); if ($up()) out(['status' => 'ok', 'msg' => 'MariaDB uruchomiona.', 'running' => true]); }
        fail('MariaDB nie odpowiedziała w 10 s. Uruchom ją w panelu XAMPP i sprawdź jej log (xampp\\mysql\\data\\mysql_error.log).');
    }

    case 'mysql_dbs': {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;connect_timeout=3', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sizes = $pdo->query("SELECT table_schema AS db, COALESCE(SUM(data_length + index_length),0) AS sz, COUNT(*) AS tb FROM information_schema.tables GROUP BY table_schema")->fetchAll(PDO::FETCH_ASSOC);
            $sys = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];
            $rows = [];
            foreach ($sizes as $r) if (!in_array($r['db'], $sys, true)) $rows[] = ['name' => $r['db'], 'size' => (int)$r['sz'], 'tables' => (int)$r['tb']];
            usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        } catch (Throwable $e) {
            fail('Brak połączenia z MariaDB (czy jest uruchomiona?).');
        }
        $dir = $xamppRoot . '\\backups_sql';
        $dumps = [];
        if (is_dir($dir)) foreach (scandir($dir) as $f) if (preg_match('/\.sql$/i', $f)) $dumps[] = ['file' => $f, 'size' => filesize($dir . '\\' . $f), 'time' => filemtime($dir . '\\' . $f)];
        usort($dumps, fn($a, $b) => $b['time'] <=> $a['time']);
        out(['status' => 'ok', 'dbs' => $rows, 'dumps' => array_slice($dumps, 0, 30), 'dir' => $dir]);
    }

    case 'mysql_dump': {
        $db = $_POST['db'] ?? '';
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;connect_timeout=3', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $names = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            fail('Brak połączenia z MariaDB (czy jest uruchomiona?).');
        }
        if (!in_array($db, $names, true) || in_array($db, ['information_schema', 'performance_schema'], true)) fail('Nie ma takiej bazy.');
        $dump = $xamppRoot . '\\mysql\\bin\\mysqldump.exe';
        if (!is_file($dump)) fail('Nie znaleziono mysqldump.exe.');
        $dir = $xamppRoot . '\\backups_sql';
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) fail('Nie można utworzyć folderu na zrzuty: ' . $dir);
        $safe = preg_replace('/[^\p{L}\p{N}_\-. ]+/u', '_', $db);
        $file = $dir . '\\' . $safe . '_' . date('Y-m-d_H-i-s') . '.sql';
        [$c, $o, $e] = run([$dump, '-uroot', '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', '--result-file=' . $file, $db], null, 300);
        if ($c !== 0 || !is_file($file) || filesize($file) < 10) { @unlink($file); fail('Zrzut nie powiódł się: ' . trim($e ?: $o)); }
        out(['status' => 'ok', 'file' => basename($file), 'size' => filesize($file), 'dir' => $dir]);
    }

    case 'dump_download': {
        header_remove('Content-Type');
        $f = basename($_POST['file'] ?? '');
        $p = $xamppRoot . '\\backups_sql\\' . $f;
        if (!preg_match('/\.sql$/i', $f) || !is_file($p)) { http_response_code(404); echo 'Nie znaleziono pliku.'; exit; }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize($p));
        readfile($p);
        exit;
    }

    case 'open_backup_dir': {
        $dir = $xamppRoot . '\\backups_sql';
        if (!is_dir($dir)) fail('Folder zrzutów jeszcze nie istnieje.');
        pclose(popen('start "" explorer "' . $dir . '"', 'r'));
        out(['status' => 'ok']);
    }

    /* ===================== AUDYT PRZED PUBLIKACJĄ ===================== */
    case 'audit': {
        $dir = projectDir();
        $findings = [];
        $add = function ($sev, $title, $detail, $files = []) use (&$findings) {
            $findings[] = ['sev' => $sev, 'title' => $title, 'detail' => $detail, 'files' => array_slice($files, 0, 12), 'total' => count($files)];
        };
        $rel = fn($p) => str_replace('\\', '/', ltrim(substr($p, strlen($dir)), '\\/'));
        $env = $git = $dumps = $phpinfo = $debug = $uploadsPhp = $secrets = $rootDb = $backupNames = [];
        $scanned = 0;

        // .git w projekcie (do 2 poziomów)
        foreach ([$dir, ...array_map(fn($d) => $dir . DIRECTORY_SEPARATOR . $d, array_filter(@scandir($dir) ?: [], fn($d) => $d[0] !== '.' && is_dir($dir . DIRECTORY_SEPARATOR . $d) && !in_array($d, T_SKIP_DIRS, true)))] as $d) {
            if (is_dir($d . DIRECTORY_SEPARATOR . '.git')) $git[] = $rel($d . DIRECTORY_SEPARATOR . '.git') ?: '.git';
        }

        $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            fn($cur) => !($cur->isDir() && in_array($cur->getFilename(), T_SKIP_DIRS, true))
        ));
        $secretRx = [
            'Klucz AWS' => '/AKIA[0-9A-Z]{16}/',
            'Klucz OpenAI/Anthropic' => '/\bsk-[A-Za-z0-9_\-]{20,}/',
            'Klucz Google API' => '/AIza[0-9A-Za-z_\-]{35}/',
            'Token GitHub' => '/\bgh[pousr]_[A-Za-z0-9]{30,}/',
            'Token Slack' => '/\bxox[baprs]-[A-Za-z0-9\-]{10,}/',
            'Klucz prywatny' => '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/',
            'Hasło/klucz w kodzie' => '/(?:password|passwd|pwd|secret|api[_-]?key|apikey|token|auth[_-]?key)[\'"]?\s*(?:=>|=|:)\s*[\'"]([^\'"\s$][^\'"]{7,})[\'"]/i',
        ];
        foreach ($it as $f) {
            if (++$scanned > 8000) break;
            $fn = strtolower($f->getFilename());
            $ext = strtolower($f->getExtension());
            $r = $rel($f->getPathname());
            if (preg_match('/^\.env(\..*)?$/', $fn) && !preg_match('/\.(example|sample|dist|template)$/', $fn)) $env[] = $r;
            if (in_array($ext, ['sql', 'sqlite', 'sqlite3', 'db', 'bak', 'old', 'orig', 'swp', 'zip', 'rar', '7z', 'gz', 'tgz', 'tar'], true) || preg_match('/(backup|dump|copy of|kopia)/i', $fn) && !in_array($ext, ['php', 'js', 'css', 'html'], true)) {
                if ($ext !== 'db' || $fn !== 'apps.db') $dumps[] = $r;
            }
            if (preg_match('/^(phpinfo|info|test|debug|adminer|shell|phpmyadmin)\.php$/', $fn)) $backupNames[] = $r;
            if ($ext === 'php' && preg_match('#(^|/)(uploads?|files|media|images|img|storage/app/public)/#i', $r)) $uploadsPhp[] = $r;
            if (in_array($ext, ['php', 'js', 'json', 'ini', 'yml', 'yaml', 'txt', 'env', 'config', 'inc', 'html'], true) || $fn === '.htaccess') {
                if ($f->getSize() > 400000) continue;
                $txt = @file_get_contents($f->getPathname());
                if ($txt === false) continue;
                $lines = null;
                if ($ext === 'php') {
                    if (preg_match('/\bphpinfo\s*\(/', $txt)) $phpinfo[] = $r;
                    if (preg_match('/ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?(1|on|true)/i', $txt) || preg_match('/error_reporting\s*\(\s*E_ALL\s*\)/', $txt)) $debug[] = $r;
                    if (preg_match('/new\s+(?:PDO|mysqli)\s*\([^;]*[\'"]root[\'"]\s*,\s*[\'"][\'"]/s', $txt) || preg_match('/[\'"](?:user|username|db_user|DB_USER)[\'"]\s*(?:=>|=|,)\s*[\'"]root[\'"]/i', $txt) && preg_match('/[\'"](?:pass|password|db_pass|DB_PASS)[\'"]\s*(?:=>|=|,)\s*[\'"][\'"]/i', $txt)) $rootDb[] = $r;
                }
                if ($ext !== 'html') {
                    $lines = preg_split('/\r?\n/', $txt);
                    foreach ($secretRx as $label => $rx) {
                        foreach ($lines as $i => $lnTxt) {
                            if (strlen($lnTxt) > 600) continue;
                            if (preg_match($rx, $lnTxt, $mm)) {
                                $val = $mm[1] ?? $mm[0];
                                // pomiń oczywiste placeholdery
                                if (preg_match('/^(your|xxx|change|example|placeholder|\*+|<|\{\{|%|\$)/i', $val) || preg_match('/(_here|_key_here|password|changeme)$/i', $val)) continue;
                                $masked = mb_substr($val, 0, 4) . str_repeat('•', 6);
                                $secrets["$r:" . ($i + 1)] ??= "$r:" . ($i + 1) . " ($label: $masked)";
                                break;
                            }
                        }
                    }
                }
            }
        }

        $htaccessNote = "Jeśli wgrywasz projekt na hosting, dodaj do .htaccess:\nRedirectMatch 404 /\\.git\nRedirectMatch 404 /\\.env\n<FilesMatch \"\\.(sql|sqlite|bak|log|zip)\$\">\n  Require all denied\n</FilesMatch>";
        if ($env) $add('high', 'Pliki .env w folderze projektu', 'Zawierają hasła i klucze. Dla przeglądarki są zwykłymi plikami: na hostingu każdy mógłby je pobrać. Trzymaj je poza folderem publicznym (patrz skill „logic-flow-gate”) albo zablokuj dostęp.', $env);
        if ($git) $add('high', 'Folder .git', 'Zawiera całą historię kodu (także starych haseł). Nie wgrywaj go na serwer publiczny ani nie zostawiaj dostępnego z przeglądarki.', $git);
        if ($uploadsPhp) $add('high', 'Pliki PHP w folderach z przesyłanymi plikami', 'Jeśli użytkownik może tam wgrać plik, mógłby wykonać własny kod na serwerze. W katalogach uploadu nie powinno być plików .php.', $uploadsPhp);
        $secrets = array_values(array_unique($secrets));
        if ($secrets) $add('high', 'Klucze i hasła w kodzie', 'Sekrety wpisane w pliki trafiają do repozytorium i na serwer. Przenieś je do pliku konfiguracyjnego poza katalogiem publicznym lub do zmiennych środowiskowych, a ujawnione klucze wymień (zmień na nowe).', $secrets);
        if ($dumps) $add('med', 'Zrzuty baz, kopie i archiwa', 'Pliki .sql, .sqlite, .bak, .zip w projekcie można zwykle pobrać po adresie URL. Usuń je z folderu publicznego przed publikacją.', $dumps);
        if ($rootDb) $add('med', 'Połączenie z bazą jako „root” bez hasła', 'W XAMPP to normalne lokalnie, ale na hostingu to ryzyko. Utwórz osobnego użytkownika bazy z hasłem i uprawnieniami tylko do tej bazy.', $rootDb);
        if ($backupNames) $add('med', 'Pliki diagnostyczne i narzędzia', 'Pliki typu phpinfo.php, info.php, test.php, adminer.php ujawniają konfigurację serwera. Usuń je przed publikacją.', $backupNames);
        if ($phpinfo) $add('med', 'Wywołania funkcji phpinfo', 'Wyświetlają pełną konfigurację PHP. Usuń przed publikacją.', $phpinfo);
        if ($debug) $add('low', 'Wyświetlanie błędów włączone w kodzie', 'display_errors oraz error_reporting z poziomem E_ALL w kodzie pokażą odwiedzającym ścieżki i szczegóły błędów. Na produkcji wyłącz wyświetlanie i loguj błędy do pliku.', $debug);
        $counts = ['high' => 0, 'med' => 0, 'low' => 0];
        foreach ($findings as $fd) $counts[$fd['sev']]++;
        out(['status' => 'ok', 'name' => projectNameOf($dir), 'findings' => $findings, 'counts' => $counts, 'scanned' => min($scanned, 8000), 'htaccess' => $htaccessNote]);
    }

    /* ===================== KREATOR NOWEGO PROJEKTU ===================== */
    case 'new_project': {
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 60 || preg_match('/[<>:"\/\\\\|?*\x00-\x1f]/u', $name) || $name[0] === '.' || substr($name, -1) === '.' || substr($name, -1) === ' ') fail('Nazwa projektu jest niepoprawna: bez znaków < > : " / \\ | ? *, bez kropki na początku i końcu.');
        if (preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])$/i', $name)) fail('Ta nazwa jest zarezerwowana w Windows.');
        $target = $docRoot . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target)) fail('Folder „' . $name . '” już istnieje w htdocs.');
        $tpl = $_POST['template'] ?? 'php';
        if (!in_array($tpl, ['empty', 'html', 'php', 'php_sqlite'], true)) fail('Nieznany szablon.');
        $desc = trim(mb_substr($_POST['desc'] ?? '', 0, 200));
        $withAi = !empty($_POST['ai_files']);
        $withGit = !empty($_POST['git']);
        if (!@mkdir($target, 0777)) fail('Nie udało się utworzyć folderu projektu.');
        $w = function ($rel, $content) use ($target) {
            $p = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            @mkdir(dirname($p), 0777, true);
            file_put_contents($p, $content);
        };
        $h = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $hd = htmlspecialchars($desc ?: 'Nowy projekt', ENT_QUOTES, 'UTF-8');
        $files = [];

        if ($tpl === 'html') {
            $w('index.html', "<!DOCTYPE html>\n<html lang=\"pl\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>$h</title>\n    <link rel=\"stylesheet\" href=\"style.css\">\n</head>\n<body>\n    <main>\n        <h1>$h</h1>\n        <p>$hd</p>\n    </main>\n    <script src=\"script.js\"></script>\n</body>\n</html>\n");
            $w('style.css', "* { box-sizing: border-box; }\nbody { margin: 0; font-family: system-ui, sans-serif; background: #f7f7f8; color: #222; }\nmain { max-width: 760px; margin: 60px auto; padding: 0 20px; }\nh1 { margin: 0 0 8px; }\n");
            $w('script.js', "'use strict';\n// Kod strony. Używaj ścieżek względnych (bez początkowego „/”), aby projekt działał w podfolderze XAMPP.\nconsole.log('$h gotowy');\n");
            $files = ['index.html', 'style.css', 'script.js'];
        } elseif ($tpl === 'php') {
            $w('index.php', "<?php\n\$title = '$h';\n?>\n<!DOCTYPE html>\n<html lang=\"pl\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title><?= htmlspecialchars(\$title) ?></title>\n    <link rel=\"stylesheet\" href=\"assets/style.css\">\n</head>\n<body>\n    <main>\n        <h1><?= htmlspecialchars(\$title) ?></h1>\n        <p>$hd</p>\n        <p><small>PHP <?= PHP_VERSION ?></small></p>\n    </main>\n</body>\n</html>\n");
            $w('assets/style.css', "* { box-sizing: border-box; }\nbody { margin: 0; font-family: system-ui, sans-serif; background: #f7f7f8; color: #222; }\nmain { max-width: 760px; margin: 60px auto; padding: 0 20px; }\n");
            $files = ['index.php', 'assets/style.css'];
        } elseif ($tpl === 'php_sqlite') {
            $w('db.php', "<?php\n// Połączenie z bazą SQLite (plik w folderze data/, zablokowany dla przeglądarki).\nfunction db(): PDO\n{\n    static \$pdo = null;\n    if (\$pdo) return \$pdo;\n    \$dir = __DIR__ . '/data';\n    if (!is_dir(\$dir)) mkdir(\$dir, 0777, true);\n    \$pdo = new PDO('sqlite:' . \$dir . '/app.sqlite');\n    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n    \$pdo->exec('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');\n    return \$pdo;\n}\n");
            $w('index.php', "<?php\nrequire __DIR__ . '/db.php';\n\$pdo = db();\nif (\$_SERVER['REQUEST_METHOD'] === 'POST' && trim(\$_POST['title'] ?? '') !== '') {\n    \$pdo->prepare('INSERT INTO items (title) VALUES (?)')->execute([trim(\$_POST['title'])]);\n    header('Location: ./');\n    exit;\n}\n\$items = \$pdo->query('SELECT * FROM items ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);\n?>\n<!DOCTYPE html>\n<html lang=\"pl\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <title>$h</title>\n    <link rel=\"stylesheet\" href=\"assets/style.css\">\n</head>\n<body>\n    <main>\n        <h1>$h</h1>\n        <p>$hd</p>\n        <form method=\"post\">\n            <input type=\"text\" name=\"title\" placeholder=\"Nowy wpis\" required>\n            <button>Dodaj</button>\n        </form>\n        <ul>\n            <?php foreach (\$items as \$it): ?>\n                <li><?= htmlspecialchars(\$it['title']) ?> <small><?= htmlspecialchars(\$it['created_at']) ?></small></li>\n            <?php endforeach; ?>\n        </ul>\n    </main>\n</body>\n</html>\n");
            $w('assets/style.css', "* { box-sizing: border-box; }\nbody { margin: 0; font-family: system-ui, sans-serif; background: #f7f7f8; color: #222; }\nmain { max-width: 760px; margin: 60px auto; padding: 0 20px; }\ninput { padding: 8px 10px; } button { padding: 8px 14px; }\nli { margin: 6px 0; } small { color: #888; }\n");
            $w('data/.htaccess', "# Blokada dostępu z przeglądarki do bazy\nRequire all denied\n");
            $files = ['index.php', 'db.php', 'assets/style.css', 'data/.htaccess'];
        }

        $w('README.md', "# $name\n\n" . ($desc ? $desc . "\n\n" : '') . "Adres lokalny: http://localhost/" . rawurlencode($name) . "/\n\n## Uruchomienie\nProjekt działa w XAMPP (Apache" . ($tpl === 'php_sqlite' ? ', SQLite' : '') . "). Otwórz adres powyżej.\n");
        $files[] = 'README.md';

        $ignore = "node_modules/\nvendor/\n.env\n*.log\n" . ($tpl === 'php_sqlite' ? "data/*.sqlite\n" : '') . ".mrp_info.json\nnotes.json\ntodo.json\n";
        $w('.gitignore', $ignore);
        $files[] = '.gitignore';

        if ($withAi) {
            $stack = ['empty' => 'do uzupełnienia', 'html' => 'HTML, CSS, JavaScript', 'php' => 'PHP, HTML, CSS', 'php_sqlite' => 'PHP, SQLite (PDO), HTML, CSS'][$tpl];
            $w('CLAUDE.md', "# Zasady projektu: $name\n\n## Opis\n" . ($desc ?: 'Krótko: co to za aplikacja i dla kogo.') . "\n\n## Środowisko\n- Lokalnie w XAMPP (Windows): http://localhost/" . rawurlencode($name) . "/\n- Stos: $stack\n\n## Zasady kodu\n- Zachowuj styl istniejącego kodu (nazwy, wcięcia, komentarze).\n- Używaj ścieżek względnych (bez początkowego „/”), bo projekt leży w podfolderze htdocs.\n- Nie zmieniaj plików poza zakresem zadania.\n- Wszystkie komunikaty dla użytkownika po polsku, wyłącznie z aplikacji (bez alert/confirm).\n- Zapytania do bazy tylko przez prepared statements; dane wyjściowe przez htmlspecialchars.\n- Sekrety i hasła trzymaj poza kodem (plik .env poza folderem publicznym).\n\n## Po zmianach\n- Sprawdź składnię (php -l) i otwórz stronę w przeglądarce.\n- Sprawdź log błędów PHP.\n- Nie commituj bez prośby.\n");
            $files[] = 'CLAUDE.md';
        }

        $w('.mrp_info.json', json_encode(['short' => $desc, 'created' => date('Y-m-d'), 'github_url' => ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $gitOk = null;
        $gitMsg = '';
        if ($withGit) {
            [$c, , $e] = git(['init'], $target, 20);
            if ($c === 0) {
                git(['add', '-A'], $target, 30);
                $env = ['GIT_AUTHOR_NAME' => 'MrPrompt', 'GIT_AUTHOR_EMAIL' => 'local@mrprompt', 'GIT_COMMITTER_NAME' => 'MrPrompt', 'GIT_COMMITTER_EMAIL' => 'local@mrprompt'];
                [$n] = git(['config', 'user.name'], $target, 8);
                if ($n === 0) $env = [];
                [$c2, $o2, $e2] = git(['commit', '-m', 'Stan początkowy projektu'], $target, 30, $env);
                $gitOk = $c2 === 0;
                if (!$gitOk) $gitMsg = trim($e2 ?: $o2);
            } else {
                $gitOk = false;
                $gitMsg = trim($e);
            }
        }
        out(['status' => 'ok', 'path' => $target, 'name' => $name, 'files' => $files, 'git' => $gitOk, 'git_msg' => $gitMsg, 'url' => 'http://localhost/' . rawurlencode($name) . '/']);
    }
}

fail('Nieznana akcja.');
