<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/daftar.php';

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assertContract(is_file($file), 'daftar.php harus tersedia');
$source = file_get_contents($file);
assertContract($source !== false, 'daftar.php harus dapat dibaca');

$required = [
    "FILTER_VALIDATE_INT",
    "SELECT id, name, price, duration_days FROM packages WHERE id = :package_id AND status = 'ACTIVE' LIMIT 1",
    "package_id",
    "$_SESSION['selected_package_id']",
    "header('Location: /checkout.php')",
];
foreach ($required as $needle) {
    assertContract(str_contains($source, $needle), "Kontrak registrasi package tidak ditemukan: {$needle}");
}

echo "PASS: registration package contract\n";
