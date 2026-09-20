<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$auth = file_get_contents($root . '/includes/auth.php');
$login = file_get_contents($root . '/login.php');
$lifecycle = file_get_contents($root . '/includes/payment_lifecycle.php');

$assertions = [
    'auth loads payment lifecycle' => str_contains($auth, "includes/payment_lifecycle.php"),
    'auth uses application access gate' => str_contains($auth, 'restockRequireApplicationAccess('),
    'lifecycle recognizes pending payments' => str_contains($lifecycle, "status IN ('PENDING', 'REJECTED')"),
    'lifecycle recognizes rejected payments' => str_contains($lifecycle, "'REJECTED'"),
    'lifecycle redirects unresolved payments' => str_contains($lifecycle, "/payment-status.php?id="),
    'login loads payment lifecycle' => str_contains($login, "includes/payment_lifecycle.php"),
    'login checks active subscription before payment redirect' => str_contains($login, 'restockHasActiveSubscription('),
    'login routes unresolved payment to status page' => str_contains($login, "/payment-status.php?id="),
];

$failed = [];
foreach ($assertions as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, "FAILED: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "PASS: unresolved payment access contract" . PHP_EOL;
