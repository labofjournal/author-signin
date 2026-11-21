PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS authors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    pen_name TEXT DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'author' CHECK (role IN ('author', 'editor')),
    failed_attempts INTEGER NOT NULL DEFAULT 0 CHECK (failed_attempts >= 0),
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
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'archived')),
    views_count INTEGER NOT NULL DEFAULT 0 CHECK (views_count >= 0),
    published_at TEXT DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER DEFAULT NULL,
    email_attempted TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT '0.0.0.0',
    user_agent TEXT NOT NULL DEFAULT 'unknown',
    status TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_articles_author_id ON articles(author_id);
CREATE INDEX IF NOT EXISTS idx_articles_slug ON articles(slug);
CREATE INDEX IF NOT EXISTS idx_login_logs_author_id ON login_logs(author_id);
CREATE INDEX IF NOT EXISTS idx_login_logs_email ON login_logs(email_attempted);
CREATE INDEX IF NOT EXISTS idx_login_logs_created_at ON login_logs(created_at);
