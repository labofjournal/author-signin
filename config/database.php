<?php

declare(strict_types=1);

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Pastikan direktori penyimpanan database SQLite tersedia sebelum membuka koneksi
    $dbPath = __DIR__ . '/../database.sqlite';
    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir) && !mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
        throw new RuntimeException('Gagal membuat direktori penyimpanan database: ' . $dbDir);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // SQLite menonaktifkan foreign key secara default per koneksi; wajib diaktifkan
    $pdo->exec('PRAGMA foreign_keys = ON;');

    initializeSqliteSchema($pdo);

    return $pdo;
}

function initializeSqliteSchema(PDO $pdo): void
{
    $sqlFile = realpath(__DIR__ . '/../database.sql');

    if ($sqlFile === false || !is_file($sqlFile) || !is_readable($sqlFile)) {
        throw new RuntimeException('File skema database.sql tidak ditemukan atau tidak dapat dibaca.');
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('File database.sql kosong atau gagal dibaca.');
    }

    $pdo->exec($sql);
}
