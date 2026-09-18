<?php
$index = file_get_contents(__DIR__ . '/../developer/index.php');
$sidebarPath = __DIR__ . '/../developer/sidebar.php';
$headerPath = __DIR__ . '/../developer/header.php';
$sidebar = file_exists($sidebarPath) ? file_get_contents($sidebarPath) : '';
$header = file_exists($headerPath) ? file_get_contents($headerPath) : '';

$checks = [
    'developer header include' => "require_once __DIR__ . '/header.php'",
    'developer sidebar include' => "require_once __DIR__ . '/sidebar.php'",
    'developer overview nav' => 'Developer Overview',
    'accounts nav' => '/developer/accounts/',
    'stores nav' => '/developer/stores/',
    'users nav' => '/developer/users/',
    'payments nav' => '/developer/payments/',
    'logout nav' => '/logout.php',
    'mobile menu' => 'toggleDeveloperSidebar()',
];

$failures = [];
foreach ($checks as $label => $needle) {
    $haystack = $index . $sidebar . $header;
    if (strpos($haystack, $needle) === false) $failures[] = $label;
}

if (strpos($sidebar, '/pages/barang') !== false || strpos($sidebar, '/pages/penjualan') !== false) {
    $failures[] = 'customer navigation leaked into developer sidebar';
}

if ($failures) {
    fwrite(STDERR, "FAIL: " . implode(', ', $failures) . "\n");
    exit(1);
}

echo "PASS: Developer navigation checks.\n";
