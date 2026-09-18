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
$normalized = preg_replace('/\s+/', ' ', $source);
assertContract($normalized !== null, 'Source registration harus dapat dinormalisasi');

$required = [
    'package_id',
    'FILTER_VALIDATE_INT',
    "SELECT id, name, price, duration_days FROM packages WHERE id = :package_id AND status = 'ACTIVE' LIMIT 1",
    "status = 'ACTIVE'",
    "\$_SESSION['selected_package_id'] = \$packageId;",
    "header('Location: /checkout.php');",
];

foreach ($required as $needle) {
    assertContract(
        str_contains($normalized, $needle),
        "Kontrak registration package tidak ditemukan: {$needle}"
    );
}

assertContract(
    str_contains($source, "filter_input(INPUT_GET, 'package_id', FILTER_VALIDATE_INT)"),
    'package_id harus dibaca dari GET dengan FILTER_VALIDATE_INT'
);

assertContract(
    str_contains($source, "\$_POST['package_id']"),
    'package_id harus dipertahankan melalui POST registration'
);

assertContract(
    str_contains($source, 'name="package_id"'),
    'Form registration harus mengirim selected package ID'
);

echo "PASS: registration package contract\n";
