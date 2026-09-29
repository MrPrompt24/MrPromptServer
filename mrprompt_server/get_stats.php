<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
@set_time_limit(20);

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max((float)$bytes, 0);
    $pow = $bytes > 0 ? (int)floor(log($bytes) / log(1024)) : 0;
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// Proste cache'owanie wyników kosztownych (procesy zewnętrzne, skan dysku)
function cached($key, $ttl, callable $fn)
{
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mrp_stats_' . md5($key) . '.json';
    if (is_file($file) && time() - filemtime($file) < $ttl) {
        $d = json_decode(@file_get_contents($file), true);
        if ($d !== null) return $d;
    }
    $d = $fn();
    @file_put_contents($file, json_encode($d));
    return $d;
}

function runCmd($cmd)
{
    if (!function_exists('shell_exec')) return null;
    $out = @shell_exec($cmd . ' 2>&1');
    $out = $out ? trim($out) : '';
    return $out !== '' ? $out : null;
}

function checkPort($port)
{
    $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

// 1. PHP
$opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
$wanted = ['curl', 'gd', 'mbstring', 'pdo_sqlite', 'sqlite3', 'pdo_mysql', 'mysqli', 'openssl', 'intl', 'zip', 'fileinfo', 'json', 'xml'];
$loaded = array_map('strtolower', get_loaded_extensions());
$php = [
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'zend' => zend_version(),
    'arch' => (PHP_INT_SIZE * 8) . '-bit',
    'ts' => PHP_ZTS ? 'TS' : 'NTS',
    'memory_limit' => ini_get('memory_limit'),
    'upload_max' => ini_get('upload_max_filesize'),
    'post_max' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time') . 's',
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => ini_get('error_reporting'),
    'timezone' => date_default_timezone_get(),
    'ini' => php_ini_loaded_file() ?: '-',
    'xdebug' => extension_loaded('xdebug') ? phpversion('xdebug') : false,
    'opcache' => $opcache ? (!empty($opcache['opcache_enabled']) ? 'On' : 'Off') : 'N/A',
    'opcache_hit_rate' => $opcache && isset($opcache['opcache_statistics']['opcache_hit_rate']) ? round($opcache['opcache_statistics']['opcache_hit_rate'], 2) . '%' : '-',
    'ext_count' => count($loaded),
    'extensions' => array_values(array_intersect($wanted, $loaded)),
    'missing_extensions' => array_values(array_diff(['pdo_sqlite', 'mysqli', 'mbstring', 'curl', 'zip'], $loaded)),
];

// 2. Serwer WWW
$server = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? '-',
    'port' => $_SERVER['SERVER_PORT'] ?? '-',
    'host' => $_SERVER['HTTP_HOST'] ?? '-',
    'docroot' => $_SERVER['DOCUMENT_ROOT'] ?? '-',
    'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
];

// 3. Dysk
$ds = @disk_total_space('.') ?: 0;
$df = @disk_free_space('.') ?: 0;
$disk = [
    'total' => formatBytes($ds),
    'free' => formatBytes($df),
    'used_percent' => $ds > 0 ? round((($ds - $df) / $ds) * 100, 1) : 0,
];

// 4. MariaDB / MySQL
$mysql = ['status' => 'Error', 'count' => '-', 'uptime' => '-', 'version' => '-', 'flavor' => 'MySQL',
    'max_connections' => '-', 'connections' => '-', 'charset' => '-', 'size' => '-', 'databases' => []];
try {
    $pdo = new PDO('mysql:host=127.0.0.1;connect_timeout=2', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2,
    ]);
    $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
    $status = [];
    foreach ($pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Questions')") as $r) {
        $status[$r['Variable_name']] = $r['Value'];
    }
    $vars = [];
    foreach ($pdo->query("SHOW GLOBAL VARIABLES WHERE Variable_name IN ('max_connections','character_set_server','version_comment')") as $r) {
        $vars[$r['Variable_name']] = $r['Value'];
    }
    $sizes = $pdo->query("SELECT table_schema AS db, COALESCE(SUM(data_length + index_length),0) AS sz
                          FROM information_schema.tables GROUP BY table_schema")->fetchAll(PDO::FETCH_KEY_PAIR);
    $system = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];
    $userDbs = [];
    $total = 0;
    foreach ($sizes as $db => $sz) {
        $total += $sz;
        if (!in_array($db, $system, true)) $userDbs[] = ['name' => $db, 'size' => formatBytes($sz)];
    }
    $mysql = [
        'status' => 'OK',
        'count' => count($sizes),
        'uptime' => sprintf('%dd %s', intdiv((int)$status['Uptime'], 86400), gmdate('H:i:s', (int)$status['Uptime'] % 86400)),
        'version' => $ver,
        'flavor' => stripos($ver . ($vars['version_comment'] ?? ''), 'mariadb') !== false ? 'MariaDB' : 'MySQL',
        'max_connections' => $vars['max_connections'] ?? '-',
        'connections' => $status['Threads_connected'] ?? '-',
        'charset' => $vars['character_set_server'] ?? '-',
        'size' => formatBytes($total),
        'databases' => array_slice($userDbs, 0, 8),
    ];
} catch (Throwable $e) {
    $mysql['error'] = 'Brak połączenia z serwerem baz danych';
}

// 5. SQLite
$sqlite = ['version' => '-', 'pdo' => in_array('pdo_sqlite', $loaded, true), 'files' => 0, 'app_db' => '-'];
try {
    if (class_exists('SQLite3')) {
        $sqlite['version'] = SQLite3::version()['versionString'];
    } elseif ($sqlite['pdo']) {
        $sqlite['version'] = (new PDO('sqlite::memory:'))->query('select sqlite_version()')->fetchColumn();
    }
    $appDb = __DIR__ . '/apps.db';
    if (is_file($appDb)) $sqlite['app_db'] = formatBytes(filesize($appDb));
} catch (Throwable $e) {
}

// 6. Projekty: liczba, pliki wg typu (PHP/HTML/JS/CSS/SQLite), ostatnio zmieniane
$root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
$scan = cached('scan:' . $root, 60, function () use ($root) {
    $skip = ['node_modules', 'vendor', '.git', '.idea', '.vscode'];
    $types = [
        'php' => ['php', 'phtml'], 'html' => ['html', 'htm'], 'js' => ['js', 'mjs', 'jsx', 'ts'],
        'css' => ['css', 'scss', 'sass', 'less'], 'sqlite' => ['sqlite', 'sqlite3', 'db'],
    ];
    $extMap = [];
    foreach ($types as $t => $exts) foreach ($exts as $e) $extMap[$e] = $t;
    $stats = [];
    foreach (array_keys($types) as $t) $stats[$t] = ['files' => 0, 'bytes' => 0];
    $recent = [];
    $projects = 0;
    $count = 0;

    foreach (@scandir($root) ?: [] as $it) {
        if ($it !== '.' && $it !== '..' && is_dir($root . DIRECTORY_SEPARATOR . $it)) $projects++;
    }

    try {
        $dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($dir, function ($cur) use ($skip) {
            return !($cur->isDir() && in_array($cur->getFilename(), $skip, true));
        });
        foreach (new RecursiveIteratorIterator($filter) as $f) {
            if (++$count > 40000) break; // ochrona przed gigantycznymi drzewami
            $ext = strtolower($f->getExtension());
            $mt = $f->getMTime();
            if (isset($extMap[$ext])) {
                $stats[$extMap[$ext]]['files']++;
                $stats[$extMap[$ext]]['bytes'] += $f->getSize();
            }
            if ($f->getFilename()[0] !== '.') $recent[] = [$mt, $f->getPathname()];
        }
    } catch (Throwable $e) {
    }
    usort($recent, fn($a, $b) => $b[0] <=> $a[0]);
    $recentOut = [];
    foreach (array_slice($recent, 0, 8) as [$mt, $path]) {
        $rel = ltrim(str_replace(['\\', $root], ['/', ''], $path), '/');
        $recentOut[] = ['file' => $rel, 'time' => date('d.m H:i', $mt)];
    }
    foreach ($stats as &$s) $s['size'] = formatBytes($s['bytes']);
    return ['count' => $projects, 'types' => $stats, 'recent' => $recentOut, 'truncated' => $count > 40000];
});

// 7. Narzędzia deweloperskie (cache 5 min – każde wywołanie to osobny proces)
$tools = cached('tools', 300, function () {
    $ver = function ($cmd, $re) {
        $o = runCmd($cmd);
        return $o && preg_match($re, $o, $m) ? $m[1] : 'Nie znaleziono';
    };
    return [
        'node' => $ver('node -v', '/v?(\d+\.\d+\.\d+)/'),
        'npm' => $ver('npm -v', '/(\d+\.\d+\.\d+)/'),
        'composer' => $ver('composer -V', '/version\s+(\d+\.\d+\.\d+)/i'),
        'git' => $ver('git --version', '/version\s+([\d.]+)/'),
    ];
});

// 8. System (Windows) – wmic bywa niedostępny, więc próbujemy też PowerShell
$sys = cached('sys', 5, function () {
    $ramTotal = $ramFree = 0;
    $cpu = null;
    $o = runCmd('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
    if ($o && preg_match('/FreePhysicalMemory=(\d+)/', $o, $m)) $ramFree = (int)$m[1];
    if ($o && preg_match('/TotalVisibleMemorySize=(\d+)/', $o, $m)) $ramTotal = (int)$m[1];
    $o = runCmd('wmic cpu get loadpercentage /Value');
    if ($o && preg_match('/LoadPercentage=(\d+)/', $o, $m)) $cpu = (int)$m[1];
    if (!$ramTotal) {
        $o = runCmd('powershell -NoProfile -Command "$o=Get-CimInstance Win32_OperatingSystem;\"$($o.FreePhysicalMemory) $($o.TotalVisibleMemorySize)\""');
        if ($o && preg_match('/(\d+)\s+(\d+)/', $o, $m)) {
            $ramFree = (int)$m[1];
            $ramTotal = (int)$m[2];
        }
    }
    return ['ramTotal' => $ramTotal, 'ramFree' => $ramFree, 'cpu' => $cpu];
});
$ramUsed = max(0, $sys['ramTotal'] - $sys['ramFree']);

$system = [
    'os' => php_uname('s') . ' ' . php_uname('r'),
    'ram_total' => formatBytes($sys['ramTotal'] * 1024),
    'ram_used' => formatBytes($ramUsed * 1024),
    'ram_percent' => $sys['ramTotal'] > 0 ? round($ramUsed / $sys['ramTotal'] * 100, 1) : 0,
    'cpu_load' => $sys['cpu'] ?? '-',
    'ports' => ['80' => checkPort(80), '443' => checkPort(443), '3306' => checkPort(3306)],
];

echo json_encode([
    'php' => $php,
    'server' => $server,
    'disk' => $disk,
    'mysql' => $mysql,
    'sqlite' => $sqlite,
    'projects' => $scan,
    'tools' => $tools,
    'system' => $system,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
