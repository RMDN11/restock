<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/checkout.php';

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assertContract(is_file($file), 'checkout.php harus tersedia');

$source = file_get_contents($file);
assertContract($source !== false, 'checkout.php harus dapat dibaca');
$normalized = preg_replace('/\s+/', ' ', $source);
assertContract($normalized !== null, 'Source checkout harus dapat dinormalisasi');

$required = [
    "require_once __DIR__ . '/includes/auth.php'",
    "\$_SESSION['account_id']",
    "\$_SESSION['store_id']",
    "\$_SESSION['selected_package_id']",
    "FROM packages",
    "status = 'ACTIVE'",
    "FROM payments",
    "payment_method = 'BANK_TRANSFER'",
    "status = 'PENDING'",
    "INSERT INTO payments",
    "expired_at",
    "CURRENT_TIMESTAMP",
    "password",
    "csrf",
];

foreach ($required as $needle) {
    assertContract(
        str_contains($normalized, $needle),
        "Kontrak checkout tidak ditemukan: {$needle}"
    );
}

assertContract(
    preg_match("/SELECT id, name, slug, price, duration_days, description FROM packages\s+WHERE id = :package_id AND status = 'ACTIVE' LIMIT 1/", $source) === 1,
    'Checkout harus re-query package ACTIVE dengan query canonical'
);

assertContract(
    str_contains($source, "':amount' => \$package['price']") ||
    str_contains($source, "':amount' => (float) \$package['price']") ||
    str_contains($source, "':amount' => \$package['price']"),
    'Amount payment harus berasal dari harga package database'
);

assertContract(
    !preg_match('/\$_POST\s*\[\s*[\'"]price[\'"]\s*\]/', $source),
    'Checkout tidak boleh mempercayai harga dari POST'
);

assertContract(
    str_contains($source, "\$_SESSION['payment_id']"),
    'payment_id harus disimpan di session'
);

echo "PASS: checkout contract\n";
