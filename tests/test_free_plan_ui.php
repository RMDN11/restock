<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/developer/free-plan/index.php';
$sidebar = $root . '/developer/includes/sidebar.php';

if (!is_file($file) || !is_file($sidebar)) {
    fwrite(STDERR, "FAIL: Free Plan UI files missing\n");
    exit(1);
}

$page = file_get_contents($file);
$nav = file_get_contents($sidebar);

$needles = [
    "INSERT INTO free_plan_invites",
    "max_uses",
    "invite_expiry",
    "free-plan.php?token=",
    "Salin Link",
    "Owner + seluruh toko account",
];

foreach ($needles as $needle) {
    if (!str_contains($page, $needle)) {
        fwrite(STDERR, "FAIL: missing Free Plan UI contract: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($nav, 'href="/developer/free-plan/"')) {
    fwrite(STDERR, "FAIL: Free Plan navigation missing\n");
    exit(1);
}

echo "PASS: Free Plan developer UI contract\n";
