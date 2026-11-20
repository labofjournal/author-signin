<?php
// Konfigurasi Koneksi Database menggunakan PHP PDO (MySQL utama + fallback SQLite otomatis)

define('DB_DRIVER', 'mysql'); // 'mysql' atau 'sqlite'
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'author_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('SQLITE_PATH', __DIR__ . '/../database.sqlite');

/**
 * Menginisialisasi skema tabel pada SQLite bila mode fallback aktif.
 */
function initializeSqliteSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS authors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            pen_name TEXT DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'author',
            failed_attempts INTEGER NOT NULL DEFAULT 0,
            locked_until TEXT DEFAULT NULL,
            last_login_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            category TEXT NOT NULL DEFAULT 'Opini',
            summary TEXT NOT NULL,
            content TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'draft',
            views_count INTEGER NOT NULL DEFAULT 0,
            published_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS login_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_id INTEGER DEFAULT NULL,
            email_attempted TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            user_agent TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL
        );
    ");
}

/**
 * Mengembalikan instance koneksi PDO yang aman dan terkelola.
 */
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (DB_DRIVER === 'mysql' && extension_loaded('pdo_mysql')) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log('MySQL PDO Error, fallback to SQLite: ' . $e->getMessage());
        }
    }

    if (extension_loaded('pdo_sqlite')) {
        try {
            $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            initializeSqliteSchema($pdo);
            return $pdo;
        } catch (PDOException $e) {
            error_log('SQLite PDO Error: ' . $e->getMessage());
        }
    }

    http_response_code(500);
    exit('Terjadi kesalahan koneksi ke server database. Pastikan driver PDO MySQL atau SQLite aktif.');
}
