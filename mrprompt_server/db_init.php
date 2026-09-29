<?php
$dbPath = __DIR__ . '/apps.db';
$db = new PDO("sqlite:$dbPath");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS apps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT, url TEXT, icon TEXT
)");

// Migracja: przypinanie aplikacji w menu
$cols = array_column($db->query("PRAGMA table_info(apps)")->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('pinned', $cols, true)) {
    $db->exec("ALTER TABLE apps ADD COLUMN pinned INTEGER DEFAULT 0");
}

// NOWA TABELA: Ulubione foldery
$db->exec("CREATE TABLE IF NOT EXISTS favorites (
    path TEXT PRIMARY KEY
)");
?>