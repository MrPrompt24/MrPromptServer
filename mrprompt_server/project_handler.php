<?php
require_once __DIR__ . '/db_init.php';
require_once __DIR__ . '/security.php';

// Ochrona: akcje zmieniające stan (POST z action lub przełączanie ulubionych)
if (isset($_POST['action']) || isset($_GET['toggle_fav'])) mrp_guard();

// ZAPISYWANIE INFORMACJI O PROJEKCIE (Metadata)
if (isset($_POST['action']) && $_POST['action'] == 'save_info') {
    $path = $_POST['path'];
    if (is_dir($path)) {
        // 1. Zapis Metadata (.mrp_info.json)
        $data = [
            'short' => $_POST['short'],
            'created' => $_POST['created'] ?: date('Y-m-d'),
            'github_url' => trim($_POST['github_url'] ?? '')
            // 'full' usuwamy lub zostawiamy dla wstecznej kompatybilności, ale teraz głównym źródłem jest notes.md
        ];
        file_put_contents($path . DIRECTORY_SEPARATOR . '.mrp_info.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    header("Location: ../index.php");
    exit;
}

// ZAPISYWANIE NOTATKI (Pojedyncza - Nowy System)
if (isset($_POST['action']) && $_POST['action'] == 'save_note') {
    $path = $_POST['path'];
    $id = $_POST['id'] ?? null; // Jeśli null, to nowa notatka
    $title = $_POST['title'];
    $content = $_POST['content'];

    if (is_dir($path)) {
        $jsonFile = $path . DIRECTORY_SEPARATOR . 'notes.json';
        $currentNotes = [];

        if (file_exists($jsonFile)) {
            $currentNotes = json_decode(file_get_contents($jsonFile), true) ?? [];
        } else {
            // Migracja starej notatki jeśli istnieje, a nie ma nowej struktury
            $oldNoteFile = $path . DIRECTORY_SEPARATOR . 'notes.md';
            if (file_exists($oldNoteFile)) {
                $oldContent = file_get_contents($oldNoteFile);
                if (!empty($oldContent)) {
                    $currentNotes[] = [
                        'id' => uniqid(),
                        'title' => 'Stare Notatki (Import)',
                        'content' => $oldContent,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        if ($id) {
            // Edycja
            foreach ($currentNotes as &$note) {
                if ($note['id'] === $id) {
                    $note['title'] = $title;
                    $note['content'] = $content;
                    $note['updated_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
        } else {
            // Nowa
            $currentNotes[] = [
                'id' => uniqid(),
                'title' => $title,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        file_put_contents($jsonFile, json_encode($currentNotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// USUWANIE NOTATKI
if (isset($_POST['action']) && $_POST['action'] == 'delete_note') {
    $path = $_POST['path'];
    $id = $_POST['id'];

    if (is_dir($path)) {
        $jsonFile = $path . DIRECTORY_SEPARATOR . 'notes.json';
        if (file_exists($jsonFile)) {
            $currentNotes = json_decode(file_get_contents($jsonFile), true) ?? [];
            $currentNotes = array_filter($currentNotes, function ($note) use ($id) {
                return $note['id'] !== $id;
            });
            // Reindex array
            $currentNotes = array_values($currentNotes);
            file_put_contents($jsonFile, json_encode($currentNotes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// DOPISYWANIE LOGU (Quick Log)
if (isset($_POST['action']) && $_POST['action'] == 'append_log') {
    $path = $_POST['path'];
    $text = $_POST['text'];
    if (is_dir($path) && !empty($text)) {
        $file = $path . DIRECTORY_SEPARATOR . 'notes.md';
        $current = file_exists($file) ? file_get_contents($file) : "";
        $entry = "### " . date('Y-m-d H:i') . "\n" . $text . "\n\n";
        file_put_contents($file, $entry . $current); // Dopisujemy NA GÓRZE
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// ZAPISYWANIE TODO
if (isset($_POST['action']) && $_POST['action'] == 'save_todo') {
    $path = $_POST['path'];
    $todos = $_POST['todos']; // JSON string
    if (is_dir($path)) {
        file_put_contents($path . DIRECTORY_SEPARATOR . 'todo.json', $todos);
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// OBSŁUGA ULUBIONYCH
if (isset($_GET['toggle_fav'])) {
    $path = $_GET['toggle_fav'];
    $stmt = $db->prepare("SELECT 1 FROM favorites WHERE path = ?");
    $stmt->execute([$path]);
    if ($stmt->fetch()) {
        $db->prepare("DELETE FROM favorites WHERE path = ?")->execute([$path]);
        $isFav = false;
    } else {
        $db->prepare("INSERT INTO favorites (path) VALUES (?)")->execute([$path]);
        $isFav = true;
    }
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'fav' => $isFav]);
        exit;
    }
    header("Location: ../index.php");
    exit;
}

// USUWANIE FOLDERU Z ZAWARTOŚCIĄ
if (isset($_POST['action']) && $_POST['action'] == 'delete_folder') {
    header('Content-Type: application/json');
    error_reporting(0); // Wyłączamy błędy, żeby nie psuły JSONa
    $path = $_POST['path'];

    if (!function_exists('deleteDir')) {
        function deleteDir($dirPath)
        {
            if (!@is_dir($dirPath)) return false;
            $items = @scandir($dirPath);
            if ($items === false) return false;
            foreach ($items as $item) {
                if ($item == '.' || $item == '..') continue;
                $itemPath = $dirPath . DIRECTORY_SEPARATOR . $item;
                if (@is_dir($itemPath)) {
                    deleteDir($itemPath);
                } else {
                    @chmod($itemPath, 0777);
                    @unlink($itemPath);
                }
            }
            @chmod($dirPath, 0777);
            return @rmdir($dirPath);
        }
    }

    // Bezpieczeństwo - sprawdzamy czy ścieżka zaczyna się od document root i nie jest samym document root
    $realPath = realpath($path);
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);

    if ($realPath && strpos($realPath, $docRoot) === 0 && $realPath !== $docRoot) {
        if (deleteDir($realPath)) {
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Błąd podczas usuwania. Być może pliki są w użyciu.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Niedozwolona operacja.']);
    }
    exit;
}

// FUNKCJA POMOCNICZA DLA TREE
function getProjectInfo($path)
{
    $file = $path . DIRECTORY_SEPARATOR . '.mrp_info.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}
