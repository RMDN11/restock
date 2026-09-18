<?php
$index = file_get_contents(__DIR__ . '/../developer/index.php');
$sidebarPath = __DIR__ . '/../developer/includes/sidebar.php';
$headerPath = __DIR__ . '/../developer/includes/header.php';
$footerPath = __DIR__ . '/../developer/includes/footer.php';
$sidebar = file_exists($sidebarPath) ? file_get_contents($sidebarPath) : '';
$header = file_exists($headerPath) ? file_get_contents($headerPath) : '';
$footer = file_exists($footerPath) ? file_get_contents($footerPath) : '';

$checks = [
    'developer header include' => "require_once __DIR__ . '/includes/header.php'",
    'developer sidebar include' => "require_once __DIR__ . '/includes/sidebar.php'",
    'developer footer include' => "require_once __DIR__ . '/includes/footer.php'",
    'developer overview nav' => '>Overview<',
    'accounts nav' => '/developer/accounts/',
    'stores nav' => '/developer/stores/',
    'logout nav' => '/logout.php',
    'mobile menu' => 'developerMenuToggle',
    'mobile sidebar behavior' => 'developerSidebarOverlay',
];

$failures = [];
$haystack = $index . $sidebar . $header . $footer;

foreach ($checks as $label => $needle) {
    if (strpos($haystack, $needle) === false) {
        $failures[] = $label;
    }
}

if (strpos($sidebar, '/pages/barang') !== false || strpos($sidebar, '/pages/penjualan') !== false) {
    $failures[] = 'customer navigation leaked into developer sidebar';
}

foreach (['/developer/users/', '/developer/payments/', '/developer/settings/'] as $unavailableRoute) {
    if (strpos($sidebar, $unavailableRoute) !== false) {
        $failures[] = 'unavailable Developer route remains in sidebar: ' . $unavailableRoute;
    }
}

if ($failures) {
    fwrite(STDERR, "FAIL: " . implode(', ', $failures) . "\n");
    exit(1);
}

echo "PASS: Developer navigation checks.\n";
