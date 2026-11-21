<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('../componnent/register.html');
}

$fullName        = sanitizeInput($_POST['full_name'] ?? '');
$penName         = sanitizeInput($_POST['pen_name'] ?? '');
$email           = sanitizeEmail($_POST['email'] ?? '');
$bio             = sanitizeInput($_POST['bio'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($fullName === '' || mb_strlen($fullName) < 3 || mb_strlen($fullName) > 100) {
    redirectTo('../componnent/register.html?error=invalid_name');
}

if (mb_strlen($penName) > 100) {
    redirectTo('../componnent/register.html?error=invalid_pen_name');
}

if ($email === false) {
    redirectTo('../componnent/register.html?error=invalid_email');
}

if (mb_strlen($bio) > 400) {
    redirectTo('../componnent/register.html?error=bio_too_long');
}

if (strlen($password) < 8) {
    redirectTo('../componnent/register.html?error=short_password');
}

if ($password !== $confirmPassword) {
    redirectTo('../componnent/register.html?error=password_mismatch');
}

try {
    $pdo = getDBConnection();

    $checkStmt = $pdo->prepare('SELECT id FROM authors WHERE email = :email LIMIT 1');
    $checkStmt->execute([':email' => $email]);

    if ($checkStmt->fetch()) {
        redirectTo('../componnent/register.html?error=email_exists');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare('
        INSERT INTO authors (full_name, pen_name, email, bio, password_hash, role)
        VALUES (:full_name, :pen_name, :email, :bio, :password_hash, :role)
    ');
    $insertStmt->execute([
        ':full_name'     => $fullName,
        ':pen_name'      => $penName !== '' ? $penName : null,
        ':email'         => $email,
        ':bio'           => $bio !== '' ? $bio : null,
        ':password_hash' => $passwordHash,
        ':role'          => 'author',
    ]);

    redirectTo('../componnent/login.html?status=registered');
} catch (Throwable $e) {
    error_log('Registration Error: ' . $e->getMessage());
    redirectTo('../componnent/register.html?error=server_error');
}
