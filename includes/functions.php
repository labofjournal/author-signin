<?php

declare(strict_types=1);

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        );

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if ($isHttps) {
            ini_set('session.cookie_secure', '1');
        }

        session_start();
    }
}

function generateCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput(string $data): string
{
    return trim(strip_tags($data));
}

function sanitizeEmail(string $email): string|false
{
    $cleaned = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if ($cleaned === false) {
        return false;
    }
    return filter_var($cleaned, FILTER_VALIDATE_EMAIL);
}

function escapeHtml(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirectTo(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function createSlug(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = trim((string) $slug, '-');
    if ($slug === '') {
        $slug = 'artikel';
    }
    return $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

function recordLoginAttempt(PDO $pdo, ?int $authorId, string $emailAttempted, string $status): bool
{
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] !== ''
        ? $_SERVER['REMOTE_ADDR']
        : '0.0.0.0';

    $rawUa = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
        ? $_SERVER['HTTP_USER_AGENT']
        : 'unknown';
    $ua = mb_substr(trim($rawUa), 0, 250, 'UTF-8');
    if ($ua === '') {
        $ua = 'unknown';
    }

    $normalizedEmail = strtolower(trim($emailAttempted));
    $normalizedStatus = trim($status) !== '' ? trim($status) : 'unknown';

    // Set author_id ke null jika id tidak ada di tabel authors agar tidak memicu error FK
    $resolvedAuthorId = null;
    if ($authorId !== null && $authorId > 0) {
        $checkStmt = $pdo->prepare('SELECT id FROM authors WHERE id = :id LIMIT 1');
        $checkStmt->execute([':id' => $authorId]);
        if ($checkStmt->fetchColumn() !== false) {
            $resolvedAuthorId = $authorId;
        }
    }

    $stmt = $pdo->prepare('
        INSERT INTO login_logs (author_id, email_attempted, ip_address, user_agent, status)
        VALUES (:author_id, :email_attempted, :ip_address, :user_agent, :status)
    ');

    return $stmt->execute([
        ':author_id'       => $resolvedAuthorId,
        ':email_attempted' => $normalizedEmail,
        ':ip_address'      => $ip,
        ':user_agent'      => $ua,
        ':status'          => $normalizedStatus,
    ]);
}
