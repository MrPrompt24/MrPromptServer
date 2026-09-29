<?php
require_once __DIR__ . '/security.php';
mrp_guard();
// mrprompt_server/file_handler.php

ini_set('display_errors', 0);
header('Content-Type: application/json');

// Basic security: allow only local edits for now or specific paths if needed
// For XAMPP local env, we can be more permissive but should still be careful.

$action = $_POST['action'] ?? '';
$path = $_POST['path'] ?? '';
$content = $_POST['content'] ?? '';

if (!$action) {
    echo json_encode(['status' => 'error', 'msg' => 'No action']);
    exit;
}

switch ($action) {
    case 'open':
        if (file_exists($path)) {
            echo json_encode([
                'status' => 'ok',
                'content' => file_get_contents($path),
                'path' => realpath($path)
            ]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'File not found']);
        }
        break;

    case 'save':
        if ($path) {
            // Ensure directory exists
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            if (file_put_contents($path, $content) !== false) {
                echo json_encode(['status' => 'ok', 'path' => realpath($path)]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Write failed']);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'No path provided']);
        }
        break;

    case 'list_dir':
        // For Open Folder dialog simulation
        if ($path === '' || $path === '/') {
            $result = [];
            foreach (range('A', 'Z') as $letter) {
                if (is_dir($letter . ':\\')) {
                    $result[] = [
                        'name' => $letter . ':\\',
                        'is_dir' => true,
                        'path' => $letter . ':\\'
                    ];
                }
            }
            echo json_encode(['status' => 'ok', 'files' => $result]);
        } else if (is_dir($path)) {
            $files = scandir($path);
            $result = [];
            foreach ($files as $f) {
                if ($f == '.' || $f == '..') continue;
                $result[] = [
                    'name' => $f,
                    'is_dir' => is_dir($path . '/' . $f),
                    'path' => realpath($path . '/' . $f)
                ];
            }
            echo json_encode(['status' => 'ok', 'files' => $result]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Dir not found']);
        }
        break;

    case 'delete':
        if (file_exists($path)) {
            if (is_dir($path)) {
                // Robust recursive delete for Windows
                function rRmDir($dir)
                {
                    if (is_dir($dir)) {
                        $objects = scandir($dir);
                        foreach ($objects as $object) {
                            if ($object != "." && $object != "..") {
                                $fullPath = $dir . DIRECTORY_SEPARATOR . $object;
                                @chmod($fullPath, 0777);
                                if (is_dir($fullPath)) {
                                    $res = rRmDir($fullPath);
                                    if ($res !== true) return $res; // Return error message
                                } else {
                                    if (!@unlink($fullPath)) {
                                        $err = error_get_last();
                                        return "Could not delete file: $fullPath (" . ($err['message'] ?? 'Unknown error') . ")";
                                    }
                                }
                            }
                        }
                        @chmod($dir, 0777);
                        if (!@rmdir($dir)) {
                            $err = error_get_last();
                            return "Could not delete directory: $dir (" . ($err['message'] ?? 'Unknown error') . ")";
                        }
                        return true;
                    }
                    return false;
                }

                $result = rRmDir($path);
                if ($result === true) {
                    echo json_encode(['status' => 'ok']);
                } else {
                    echo json_encode(['status' => 'error', 'msg' => $result]);
                }
            } else {
                @chmod($path, 0777);
                if (@unlink($path)) {
                    echo json_encode(['status' => 'ok']);
                } else {
                    $err = error_get_last();
                    echo json_encode(['status' => 'error', 'msg' => 'Could not delete file: ' . ($err['message'] ?? 'Unknown error')]);
                }
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Path not found']);
        }
        break;

    case 'copy_dir':
        $dest_root = $_POST['dest_root'] ?? '';
        if (!$dest_root || !is_dir($dest_root)) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid destination root']);
            exit;
        }

        if (!file_exists($path) || !is_dir($path)) {
            echo json_encode(['status' => 'error', 'msg' => 'Source not found or not a directory']);
            exit;
        }

        $sourceName = basename($path);
        $timestamp = date('Y-m-d_H-i'); // Minutes precision
        $destName = $timestamp . '_' . $sourceName;
        $destPath = rtrim($dest_root, '/\\') . DIRECTORY_SEPARATOR . $destName;

        // Recursive Copy Function
        function recurse_copy($src, $dst)
        {
            $dir = opendir($src);
            @mkdir($dst);
            while (false !== ($file = readdir($dir))) {
                if (($file != '.') && ($file != '..')) {
                    if (is_dir($src . '/' . $file)) {
                        recurse_copy($src . '/' . $file, $dst . '/' . $file);
                    } else {
                        copy($src . '/' . $file, $dst . '/' . $file);
                    }
                }
            }
            closedir($dir);
        }

        try {
            recurse_copy($path, $destPath);
            echo json_encode(['status' => 'ok', 'dest' => $destPath]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        }
        break;

    case 'search':
        $query = $_POST['query'] ?? '';
        if (!$query || !$path || !is_dir($path)) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid params']);
            exit;
        }

        $results = [];
        $dir = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir);

        foreach ($iterator as $file) {
            if ($file->isDir()) continue;

            // Skip common ignore folders
            if (
                strpos($file->getPathname(), '.git') !== false ||
                strpos($file->getPathname(), 'node_modules') !== false ||
                strpos($file->getPathname(), 'vendor') !== false
            ) {
                continue;
            }

            // Allow only text files
            $ext = $file->getExtension();
            if (!in_array(strtolower($ext), ['php', 'js', 'html', 'css', 'txt', 'json', 'md', 'xml', 'sql'])) continue;

            $lines = file($file->getPathname());
            foreach ($lines as $lineNum => $line) {
                if (strpos($line, $query) !== false) {
                    $results[] = [
                        'file' => $file->getPathname(),
                        'line' => $lineNum + 1,
                        'content' => trim($line)
                    ];
                    if (count($results) >= 100) break 2; // Limit results
                }
            }
        }

        echo json_encode(['status' => 'ok', 'results' => $results]);
        break;

    case 'rename':
        $newPath = $_POST['new_path'] ?? '';
        if (!$path || !$newPath) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid params']);
            exit;
        }
        if (file_exists($newPath)) {
            echo json_encode(['status' => 'error', 'msg' => 'Destination exists']);
            exit;
        }
        if (rename($path, $newPath)) {
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Rename failed']);
        }
        break;

    case 'download':
        if (file_exists($path)) {
            $filename = basename($path);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        } else {
            http_response_code(404);
            echo "File not found";
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'msg' => 'Invalid action']);
        break;
}
