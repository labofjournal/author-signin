<?php

declare(strict_types=1);

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

if (mb_strlen($password) < 8) {
    redirectTo('../componnent/register.html?error=weak_password');
}

if (!hash_equals($password, $confirmPassword)) {
    redirectTo('../componnent/register.html?error=password_mismatch');
}

$pdo = getDbConnection();

$checkStmt = $pdo->prepare('SELECT id FROM authors WHERE email = :email LIMIT 1');
$checkStmt->execute([':email' => strtolower($email)]);

if ($checkStmt->fetchColumn() !== false) {
    recordLoginAttempt($pdo, null, strtolower($email), 'register_email_exists');
    redirectTo('../componnent/register.html?error=email_exists');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    redirectTo('../componnent/register.html?error=hash_failed');
}

$insertStmt = $pdo->prepare('
    INSERT INTO authors (full_name, email, pen_name, bio, password_hash, role)
    VALUES (:full_name, :email, :pen_name, :bio, :password_hash, :role)
');

$insertStmt->execute([
    ':full_name'     => $fullName,
    ':email'         => strtolower($email),
    ':pen_name'      => $penName !== '' ? $penName : null,
    ':bio'           => $bio !== '' ? $bio : null,
    ':password_hash' => $passwordHash,
    ':role'          => 'author',
]);

$newAuthorId = (int) $pdo->lastInsertId();
recordLoginAttempt($pdo, $newAuthorId, strtolower($email), 'registered');

session_regenerate_id(true);
$_SESSION['author_id'] = $newAuthorId;
$_SESSION['full_name'] = $fullName;
$_SESSION['pen_name']  = $penName !== '' ? $penName : $fullName;
$_SESSION['email']     = strtolower($email);
$_SESSION['role']      = 'author';

redirectTo('dashboard.php');
