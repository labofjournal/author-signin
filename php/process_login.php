<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('../componnent/login.html');
}

$email    = sanitizeEmail($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === false || $password === '') {
    redirectTo('../componnent/login.html?error=empty_fields');
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare('
        SELECT id, full_name, pen_name, email, password_hash, role, failed_attempts, locked_until
        FROM authors
        WHERE email = :email
        LIMIT 1
    ');
    $stmt->execute([':email' => $email]);
    $author = $stmt->fetch();

    $nowTs = time();

    if ($author && !empty($author['locked_until'])) {
        $lockTs = strtotime((string) $author['locked_until']);
        if ($lockTs !== false && $lockTs > $nowTs) {
            recordLoginAttempt($pdo, (int) $author['id'], $email, 'locked');
            redirectTo('../componnent/login.html?error=account_locked');
        }
    }

    if (!$author || !password_verify($password, $author['password_hash'])) {
        if ($author) {
            $attempts = (int) $author['failed_attempts'] + 1;
            $lockedUntil = null;

            if ($attempts >= 5) {
                $lockedUntil = date('Y-m-d H:i:s', $nowTs + (15 * 60));
                $attempts = 0;
            }

            $updFail = $pdo->prepare('
                UPDATE authors
                SET failed_attempts = :attempts, locked_until = :locked_until
                WHERE id = :id
            ');
            $updFail->execute([
                ':attempts'     => $attempts,
                ':locked_until' => $lockedUntil,
                ':id'           => $author['id'],
            ]);

            recordLoginAttempt($pdo, (int) $author['id'], $email, 'failed');
        } else {
            recordLoginAttempt($pdo, null, $email, 'failed');
        }

        redirectTo('../componnent/login.html?error=invalid');
    }

    if (password_needs_rehash($author['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehashStmt = $pdo->prepare('UPDATE authors SET password_hash = :hash WHERE id = :id');
        $rehashStmt->execute([
            ':hash' => $newHash,
            ':id'   => $author['id'],
        ]);
    }

    $resetStmt = $pdo->prepare('
        UPDATE authors
        SET failed_attempts = 0, locked_until = NULL, last_login_at = :last_login
        WHERE id = :id
    ');
    $resetStmt->execute([
        ':last_login' => date('Y-m-d H:i:s', $nowTs),
        ':id'         => $author['id'],
    ]);

    recordLoginAttempt($pdo, (int) $author['id'], $email, 'success');

    session_regenerate_id(true);
    $_SESSION['author_id']    = (int) $author['id'];
    $_SESSION['author_name']  = $author['full_name'];
    $_SESSION['author_pen']   = $author['pen_name'] ?? '';
    $_SESSION['author_email'] = $author['email'];
    $_SESSION['author_role']  = $author['role'] ?? 'author';
    $_SESSION['logged_in_at'] = $nowTs;

    redirectTo('dashboard.php?status=welcome');
} catch (Throwable $e) {
    error_log('Login Error: ' . $e->getMessage());
    redirectTo('../componnent/login.html?error=server_error');
}
