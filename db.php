<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

function memoAppDatabase(): PDO
{
    static $db = null;

    if ($db instanceof PDO) {
        return $db;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    date_default_timezone_set('Asia/Tokyo');

    $databasePath = getenv('MEMO_DB_PATH') ?: __DIR__.DIRECTORY_SEPARATOR.'memo.sqlite';
    $databaseDir = dirname($databasePath);

    if (! is_dir($databaseDir) && ! mkdir($databaseDir, 0777, true) && ! is_dir($databaseDir)) {
        throw new RuntimeException('Unable to create database directory: '.$databaseDir);
    }

    $db = new PDO('sqlite:'.$databasePath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS memos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT,
        content TEXT,
        remind_at TEXT,
        repeat_mode TEXT,
        repeat_config TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    $columnStatement = $db->query('PRAGMA table_info(memos)');
    $columns = $columnStatement === false ? [] : $columnStatement->fetchAll();
    $hasUserId = false;
    $hasRemindAt = false;
    $hasRepeatMode = false;
    $hasRepeatConfig = false;

    foreach ($columns as $column) {
        if (! is_array($column)) {
            continue;
        }

        $columnName = $column['name'] ?? null;

        if ($columnName === 'user_id') {
            $hasUserId = true;
        }

        if ($columnName === 'remind_at') {
            $hasRemindAt = true;
        }

        if ($columnName === 'repeat_mode') {
            $hasRepeatMode = true;
        }

        if ($columnName === 'repeat_config') {
            $hasRepeatConfig = true;
        }
    }

    if (! $hasUserId) {
        $db->exec('ALTER TABLE memos ADD COLUMN user_id INTEGER');
    }

    if (! $hasRemindAt) {
        $db->exec('ALTER TABLE memos ADD COLUMN remind_at TEXT');
    }

    if (! $hasRepeatMode) {
        $db->exec('ALTER TABLE memos ADD COLUMN repeat_mode TEXT');
    }

    if (! $hasRepeatConfig) {
        $db->exec('ALTER TABLE memos ADD COLUMN repeat_config TEXT');
    }

    return $db;
}

return memoAppDatabase();
