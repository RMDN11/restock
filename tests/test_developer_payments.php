<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/developer/payments/index.php',
    $root . '/developer/payments/view.php',
    $root . '/developer/includes/sidebar.php',
];

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

foreach ($files as $file) {
    assertContract(is_file($file), 'File payment developer harus tersedia: ' . $file);
}

$index = file_get_contents($files[0]);
$view = file_get_contents($files[1]);
$sidebar = file_get_contents($files[2]);

assertContract(str_contains($index, 'FROM payments'), 'Index harus membaca payments');
assertContract(str_contains($index, "pay.status"), 'Index harus mendukung filter status payment');
assertContract(str_contains($index, '/developer/payments/view.php?id='), 'Index harus memiliki link detail payment');

foreach ([
    'csrf_token',
    "action",
    "'VERIFY'",
    "'REJECT'",
    "SET status='VERIFIED'",
    "SET status='REJECTED'",
    'verified_by',
    'verified_at',
    'rejection_reason',
    "status='PENDING'",
    'hash_equals',
] as $needle) {
    assertContract(str_contains($view, $needle), 'Kontrak payment verification tidak ditemukan: ' . $needle);
}

assertContract(str_contains($sidebar, '/developer/payments/'), 'Sidebar harus memiliki menu Pembayaran');

echo "PASS: payment verification developer contract\n";
