<?php
declare(strict_types=1);

$files = [
    __DIR__ . '/../daftar-paket.php',
    __DIR__ . '/../daftar.php',
    __DIR__ . '/../checkout.php',
    __DIR__ . '/../includes/auth.php',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "FAIL: missing " . $file . PHP_EOL);
        exit(1);
    }
}

$packagePage = file_get_contents($files[0]);
$registration = file_get_contents($files[1]);
$checkout = file_get_contents($files[2]);
$auth = file_get_contents($files[3]);

$checks = [
    'package page keeps checkout context' =>
        str_contains($packagePage, '$hasCheckoutContext') &&
        str_contains($packagePage, "in_array((string) (\$_SESSION['checkout_origin'] ?? ''), ['/daftar-paket.php', '/renewal.php'], true)"),
    'package page redirects ordinary logged-in users' =>
        str_contains($packagePage, "if (!empty(\$_SESSION['user_id']) && !$hasCheckoutContext)") &&
        str_contains($packagePage, "header('Location: /');"),
    'registration stores selected package after commit' =>
        str_contains($registration, "\$_SESSION['selected_package_id']") &&
        str_contains($registration, "\$pdo->commit()"),
    'registration redirects to checkout' =>
        str_contains($registration, "header('Location: /checkout.php')"),
    'checkout re-resolves tier price' =>
        str_contains($checkout, "restockFindPackagePriceTier") &&
        str_contains($checkout, "':amount' => \$pricingTier['price']"),
    'checkout creates pending bank transfer' =>
        str_contains($checkout, "'BANK_TRANSFER'") &&
        str_contains($checkout, "'PENDING'"),
    'application root remains subscription-gated' =>
        str_contains($auth, "'/index.php'") &&
        str_contains($auth, "restockRequireActiveSubscription"),
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) {
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, "FAIL\n");
    foreach ($failed as $name) {
        fwrite(STDERR, " - " . $name . PHP_EOL);
    }
    exit(1);
}

echo "PASS: Task 15A onboarding/payment gate contract" . PHP_EOL;
