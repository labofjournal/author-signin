-- Skema Database Lengkap untuk Sistem Author Sign-In & Portal Artikel
CREATE DATABASE IF NOT EXISTS author_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE author_portal;

CREATE TABLE IF NOT EXISTS authors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    pen_name VARCHAR(100) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('author', 'editor') NOT NULL DEFAULT 'author',
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    category VARCHAR(60) NOT NULL DEFAULT 'Opini',
    summary VARCHAR(300) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    views_count INT UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id INT UNSIGNED DEFAULT NULL,
    email_attempted VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    status ENUM('success', 'failed', 'locked') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_logs_author FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
