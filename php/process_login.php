<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('../componnent/login.html');
}

$rawEmail = trim((string) ($_POST['email'] ?? ''));
$email    = sanitizeEmail($rawEmail);
$password = (string) ($_POST['password'] ?? '');

$pdo = getDbConnection();

if ($email === false) {
    recordLoginAttempt($pdo, null, strtolower($rawEmail), 'invalid_email');
    redirectTo('../componnent/login.html?error=invalid_email');
}

if ($password === '') {
    recordLoginAttempt($pdo, null, strtolower($email), 'empty_password');
    redirectTo('../componnent/login.html?error=empty_password');
}

$normalizedEmail = strtolower($email);

$stmt = $pdo->prepare('
    SELECT id, full_name, pen_name, email, password_hash, role, failed_attempts, locked_until
    FROM authors
    WHERE email = :email
    LIMIT 1
');
$stmt->execute([':email' => $normalizedEmail]);
$author = $stmt->fetch();

if ($author === false) {
    recordLoginAttempt($pdo, null, $normalizedEmail, 'user_not_found');
    redirectTo('../componnent/login.html?error=invalid_credentials');
}

$authorId = (int) $author['id'];

if (!empty($author['locked_until'])) {
    $lockTimestamp = strtotime((string) $author['locked_until']);
    if ($lockTimestamp !== false && $lockTimestamp > time()) {
        recordLoginAttempt($pdo, $authorId, $normalizedEmail, 'account_locked');
        redirectTo('../componnent/login.html?error=account_locked');
    }
}

if (!password_verify($password, (string) $author['password_hash'])) {
    $failedAttempts = (int) $author['failed_attempts'] + 1;
    $lockedUntil = null;

    if ($failedAttempts >= 5) {
        $lockedUntil = gmdate('Y-m-d H:i:s', time() + 900);
    }

    $failStmt = $pdo->prepare('
        UPDATE authors
        SET failed_attempts = :failed_attempts,
            locked_until = :locked_until,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ');
    $failStmt->execute([
        ':failed_attempts' => $failedAttempts,
        ':locked_until'    => $lockedUntil,
        ':id'              => $authorId,
    ]);

    recordLoginAttempt($pdo, $authorId, $normalizedEmail, 'failed_password');
    redirectTo('../componnent/login.html?error=invalid_credentials');
}

if (password_needs_rehash((string) $author['password_hash'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    if ($newHash !== false) {
        $rehashStmt = $pdo->prepare('
            UPDATE authors
            SET password_hash = :password_hash
            WHERE id = :id
        ');
        $rehashStmt->execute([
            ':password_hash' => $newHash,
            ':id'              => $authorId,
        ]);
    }
}

$successStmt = $pdo->prepare('
    UPDATE authors
    SET failed_attempts = 0,
        locked_until = NULL,
        last_login_at = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :id
');
$successStmt->execute([':id' => $authorId]);

recordLoginAttempt($pdo, $authorId, $normalizedEmail, 'success');

session_regenerate_id(true);
$_SESSION['author_id'] = $authorId;
$_SESSION['full_name'] = (string) $author['full_name'];
$_SESSION['pen_name']  = !empty($author['pen_name']) ? (string) $author['pen_name'] : (string) $author['full_name'];
$_SESSION['email']     = (string) $author['email'];
$_SESSION['role']      = (string) $author['role'];

redirectTo('dashboard.php');
