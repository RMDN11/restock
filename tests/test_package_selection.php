<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/paket/index.php';

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assertContract(is_file($file), 'paket/index.php harus tersedia');

$source = file_get_contents($file);
assertContract($source !== false, 'paket/index.php harus dapat dibaca');

$required = [
    "SELECT id, name, slug, price, duration_days, description FROM packages WHERE status = 'ACTIVE' ORDER BY price ASC, id ASC",
    "status = 'ACTIVE'",
    'package_id',
    'http_build_query',
    '$pdo->prepare(',
    "htmlspecialchars",
];

foreach ($required as $needle) {
    assertContract(
        str_contains($source, $needle),
        "Kontrak package selection tidak ditemukan: {$needle}"
    );
}

assertContract(
    str_contains($source, "href=\"/daftar.php?"),
    'Harus menyediakan link menuju pendaftaran'
);

assertContract(
    str_contains($source, "['package_id' => (int)"),
    'package_id pada link pendaftaran harus berasal dari ID package yang di-cast ke integer'
);

echo "PASS: package selection contract\n";
