<?php
// Fungsi bantuan sanitasi, CSRF token, rate limit/lockout, slug, dan manajemen sesi

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }

        session_start();
    }
}

function generateCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeInput(string $data): string
{
    return trim(strip_tags($data));
}

function sanitizeEmail(string $email)
{
    $cleaned = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($cleaned, FILTER_VALIDATE_EMAIL);
}

function escapeHtml(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirectTo(string $url): void
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

function recordLoginAttempt(PDO $pdo, ?int $authorId, string $emailAttempted, string $status): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 250);

    $stmt = $pdo->prepare('
        INSERT INTO login_logs (author_id, email_attempted, ip_address, user_agent, status)
        VALUES (:author_id, :email_attempted, :ip_address, :user_agent, :status)
    ');
    $stmt->execute([
        ':author_id'       => $authorId,
        ':email_attempted' => $emailAttempted,
        ':ip_address'      => $ip,
        ':user_agent'      => $ua,
        ':status'          => $status,
    ]);
}
