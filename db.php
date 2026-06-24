<?php
session_start();
date_default_timezone_set('Asia/Tokyo');

$databasePath = getenv('MEMO_DB_PATH') ?: __DIR__ . DIRECTORY_SEPARATOR . 'memo.sqlite';

if (!is_dir(dirname($databasePath))) {
    mkdir(dirname($databasePath), 0777, true);
}

$db = new PDO('sqlite:' . $databasePath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS memos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    title TEXT,
    content TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$columns = $db->query("PRAGMA table_info(memos)")->fetchAll();
$hasUserId = false;

foreach ($columns as $column) {
    if ($column['name'] === 'user_id') {
        $hasUserId = true;
        break;
    }
}

if (!$hasUserId) {
    $db->exec("ALTER TABLE memos ADD COLUMN user_id INTEGER");
}
