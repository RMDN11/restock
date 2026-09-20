<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$index = file_get_contents($root . '/index.php');
$header = file_get_contents($root . '/includes/header.php');
$mobileDashboard = file_get_contents($root . '/includes/mobile_dashboard.php');
$css = file_get_contents($root . '/assets/css/app.css');

$checks = [
    'mobile dashboard partial is included' => str_contains($index, "includes/mobile_dashboard.php"),
    'desktop dashboard keeps desktop class' => str_contains($index, 'desktop-dashboard'),
    'mobile bottom navigation exists' => str_contains($header, 'mobile-app-bottom-nav'),
    'primary mobile action is new sale' => str_contains($header, '/pages/penjualan/create.php'),
    'mobile dashboard has quick access' => str_contains($mobileDashboard, 'Akses Cepat'),
    'mobile dashboard has low stock' => str_contains($mobileDashboard, 'Barang Hampir Habis'),
    'mobile dashboard has recent sales' => str_contains($mobileDashboard, 'Penjualan Terbaru'),
    'mobile app shell has safe area support' => str_contains($css, 'env(safe-area-inset-bottom)'),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAILED: {$name}" . PHP_EOL);
        exit(1);
    }
}

echo "PASS: mobile app shell contract" . PHP_EOL;
