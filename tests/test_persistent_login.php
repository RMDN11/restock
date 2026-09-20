<?php
declare(strict_types=1);

$files = [
    __DIR__ . '/../includes/auth.php',
    __DIR__ . '/../login.php',
    __DIR__ . '/../logout.php',
    __DIR__ . '/../database/migrations/016_persistent_login_tokens.sql',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "FAIL: missing " . $file . PHP_EOL);
        exit(1);
    }
}

$auth = file_get_contents($files[0]);
$login = file_get_contents($files[1]);
$logout = file_get_contents($files[2]);
$migration = file_get_contents($files[3]);

$checks = [
    'auth has remember cookie constant' =>
        str_contains($auth, "RESTOCK_REMEMBER_COOKIE"),
    'auth restores remembered session' =>
        str_contains($auth, "restoreRestockRememberedSession") &&
        str_contains($auth, "remember_tokens"),
    'login issues secure random token' =>
        str_contains($login, "random_bytes(32)") &&
        str_contains($login, "hash('sha256'"),
    'login stores 30 day expiry' =>
        str_contains($login, "INTERVAL 30 DAY") &&
        str_contains($login, "RESTOCK_SESSION_LIFETIME = 2592000"),
    'logout revokes remember token' =>
        str_contains($logout, "DELETE FROM remember_tokens"),
    'remember token is unique and hashed' =>
        str_contains($migration, "UNIQUE KEY uq_remember_tokens_hash") &&
        str_contains($migration, "token_hash CHAR(64)"),
    'remember token belongs to user' =>
        str_contains($migration, "FOREIGN KEY (user_id) REFERENCES users(id)"),
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, "FAIL" . PHP_EOL);
    foreach ($failed as $name) {
        fwrite(STDERR, " - " . $name . PHP_EOL);
    }
    exit(1);
}

echo "PASS: persistent login contract" . PHP_EOL;
