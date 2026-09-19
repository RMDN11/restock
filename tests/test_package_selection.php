<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$canonical = $root . '/daftar-paket.php';
$legacy = $root . '/paket/index.php';

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assertContract(is_file($canonical), 'daftar-paket.php harus tersedia sebagai package-selection canonical');
assertContract(is_file($legacy), 'paket/index.php harus tersedia sebagai compatibility route');

$canonicalSource = file_get_contents($canonical);
$legacySource = file_get_contents($legacy);

assertContract($canonicalSource !== false, 'daftar-paket.php harus dapat dibaca');
assertContract($legacySource !== false, 'paket/index.php harus dapat dibaca');

$normalized = preg_replace('/\\s+/', ' ', $canonicalSource);
assertContract($normalized !== null, 'Source canonical package selection harus dapat dinormalisasi');

$required = [
    'FROM packages',
    "status = 'ACTIVE'",
    'package_id',
    'http_build_query',
    'htmlspecialchars',
    '/daftar.php?package_id=',
    '/daftar.php?free=1',
];

foreach ($required as $needle) {
    assertContract(
        str_contains($normalized, $needle),
        "Kontrak canonical package selection tidak ditemukan: {$needle}"
    );
}

assertContract(
    str_contains($legacySource, "header('Location: ' . \$location"),
    'Route legacy /paket/ harus mengarahkan ke halaman paket canonical'
);

assertContract(
    str_contains($legacySource, "/daftar-paket.php"),
    'Route legacy /paket/ harus menunjuk ke /daftar-paket.php'
);

echo "PASS: canonical package selection contract\n";
