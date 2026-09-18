<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/developer/subscriptions/index.php';

if (!is_file($file)) {
    fwrite(STDERR, "FAIL: developer subscription page missing\n");
    exit(1);
}

$content = file_get_contents($file);

$needles = [
    "FROM subscriptions sub",
    "UPDATE subscriptions",
    "sub.status = :status",
    "sub.payment_id",
    "/developer/payments/view.php?id=",
    "Nilai Paket",
    "ACTIVE",
    "EXPIRED",
    "CANCELLED",
    "LIMIT 200",
];

foreach ($needles as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL: missing contract: {$needle}\n");
        exit(1);
    }
}

echo "PASS: developer subscription page contract\n";
