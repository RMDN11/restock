<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/free-plan.php',
    $root . '/includes/free_plan.php',
    $root . '/login.php',
    $root . '/daftar.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "FAIL: missing file " . basename($file) . "\n");
        exit(1);
    }
}

$freePlan = file_get_contents($root . '/free-plan.php');
$helper = file_get_contents($root . '/includes/free_plan.php');
$login = file_get_contents($root . '/login.php');
$register = file_get_contents($root . '/daftar.php');

$contracts = [
    [$freePlan, "restockFreePlanFindInvite", "Free Plan entry validation"],
    [$freePlan, "pending_free_plan_token", "Free Plan pending session"],
    [$helper, "restockFreePlanRedeem", "Free Plan redemption helper"],
    [$helper, "plan_type = 'FREE'", "account Free Plan activation"],
    [$helper, "used_count = used_count + 1", "invite quota consumption"],
    [$login, "pending_free_plan_token", "normal login redemption"],
    [$login, "restockFreePlanRedeem", "normal login redemption helper"],
    [$register, "free_token", "Free Plan registration token"],
    [$register, "restockFreePlanRedeem", "registration redemption"],
];

foreach ($contracts as [$content, $needle, $label]) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL: missing {$label}: {$needle}\n");
        exit(1);
    }
}

echo "PASS: Free Plan onboarding/redeem contract\n";
