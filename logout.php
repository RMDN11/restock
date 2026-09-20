<?php

require_once __DIR__ . '/config/database.php';

const RESTOCK_REMEMBER_COOKIE = 'restock_remember';

session_start();

$rawToken = trim((string) ($_COOKIE[RESTOCK_REMEMBER_COOKIE] ?? ''));

if ($rawToken !== '' && preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare(
        "DELETE FROM remember_tokens WHERE token_hash = :token_hash"
    );
    $stmt->execute([':token_hash' => $tokenHash]);
}

setcookie(
    RESTOCK_REMEMBER_COOKIE,
    '',
    [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]
);

$_SESSION = [];
session_destroy();

header('Location: /login.php');
exit;
