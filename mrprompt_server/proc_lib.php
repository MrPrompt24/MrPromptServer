<?php
// Wspólne funkcje pomocnicze do uruchamiania procesów (bez powłoki, z limitem czasu).
if (!function_exists('run')) {
    // Uruchamia proces bez powłoki (brak problemów z cudzysłowami), z limitem czasu.
    function run(array $cmd, $cwd = null, $timeout = 25, array $env = [])
    {
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $envFull = array_merge(getenv() ?: [], ['GIT_TERMINAL_PROMPT' => '0', 'LC_ALL' => 'C.UTF-8'], $env);
        $proc = @proc_open($cmd, $desc, $pipes, $cwd, $envFull);
        if (!is_resource($proc)) return [-1, '', 'Nie można uruchomić procesu'];
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $o = $e = '';
        $end = microtime(true) + $timeout;
        while (true) {
            $st = proc_get_status($proc);
            $o .= stream_get_contents($pipes[1]);
            $e .= stream_get_contents($pipes[2]);
            if (!$st['running']) break;
            if (microtime(true) > $end) {
                proc_terminate($proc);
                $e .= "\n[przerwano po {$timeout}s]";
                break;
            }
            usleep(15000);
        }
        $o .= stream_get_contents($pipes[1]);
        $e .= stream_get_contents($pipes[2]);
        foreach ($pipes as $p) fclose($p);
        $code = proc_close($proc);
        if (isset($st['exitcode']) && $st['exitcode'] >= 0) $code = $st['exitcode'];
        return [$code, $o, $e];
    }

    function git(array $args, $cwd, $timeout = 25, array $env = [])
    {
        return run(array_merge(['git', '-c', 'safe.directory=*', '-c', 'core.quotepath=off'], $args), $cwd, $timeout, $env);
    }

    function phpBin()
    {
        foreach (['C:\\xampp\\php\\php.exe', dirname((string)php_ini_loaded_file()) . '\\php.exe'] as $p) {
            if (is_file($p)) return $p;
        }
        return 'php';
    }
}
