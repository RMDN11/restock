<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$checks = [
    'developer auth include' => $root . '/includes/developer_auth.php',
    'database config' => $root . '/config/database.php',
    'developer header' => $root . '/developer/includes/header.php',
    'developer sidebar' => $root . '/developer/includes/sidebar.php',
    'developer footer' => $root . '/developer/includes/footer.php',
];

foreach ($checks as $label => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "FAIL: {$label} missing: {$file}\n");
        exit(1);
    }
}

$subscriptionPage = $root . '/developer/subscriptions/index.php';
$content = file_get_contents($subscriptionPage);

$required = [
    "require_once __DIR__ . '/../includes/developer_auth.php';",
    "require_once __DIR__ . '/../includes/header.php';",
    "require_once __DIR__ . '/../includes/sidebar.php';",
    "require_once __DIR__ . '/../includes/footer.php';",
    'FROM subscriptions sub',
];

foreach ($required as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL: missing dependency contract: {$needle}\n");
        exit(1);
    }
}

echo "PASS: developer subscription dependency diagnostics\n";
