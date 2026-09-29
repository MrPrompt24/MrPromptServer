<?php
require_once __DIR__ . '/security.php';
mrp_guard();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action == 'open_cmd') {
    // Open Windows Command Prompt
    // pclose(popen('start cmd', 'r')); // basic
    // better: use COM or exec with start
    try {
        pclose(popen('start cmd', 'r'));
        echo json_encode(['status' => 'ok', 'msg' => 'CMD launched']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

if ($action == 'run_cmd') {
    $cmd = $_POST['cmd'] ?? '';
    if (!$cmd) {
        echo json_encode(['status' => 'error', 'msg' => 'No command']);
        exit;
    }

    // Execute command
    try {
        // limit to git for now for safety, or just let it fly since it's local
        // $output = shell_exec($cmd . " 2>&1");
        // Use exec to get exit code
        $output = [];
        $return_var = 0;
        exec($cmd . " 2>&1", $output, $return_var);

        echo json_encode([
            'status' => 'ok',
            'output' => implode("\n", $output),
            'code' => $return_var
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
