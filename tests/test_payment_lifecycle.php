<?php
declare(strict_types=1);

/**
 * Static contract checks for Task 15B-1.
 * Runtime database tests require the application's MySQL environment.
 */

$root = dirname(__DIR__);

$helper = file_get_contents($root . '/includes/payment_lifecycle.php');
$checkout = file_get_contents($root . '/checkout.php');
$paymentsIndex = file_get_contents($root . '/developer/payments/index.php');
$paymentView = file_get_contents($root . '/developer/payments/view.php');

$assertions = [
    'helper exists' => $helper !== false,
    'helper updates PENDING to EXPIRED' => str_contains($helper, "SET status = 'EXPIRED'"),
    'helper only expires PENDING payments' => str_contains($helper, "WHERE status = 'PENDING'"),
    'helper checks expired_at' => str_contains($helper, 'expired_at <= CURRENT_TIMESTAMP'),
    'checkout loads lifecycle helper' => str_contains($checkout, "includes/payment_lifecycle.php"),
    'checkout syncs expired payments' => str_contains($checkout, 'restockExpirePendingPayments($pdo);'),
    'developer payment list loads lifecycle helper' => str_contains($paymentsIndex, "includes/payment_lifecycle.php"),
    'developer payment list syncs expired payments' => str_contains($paymentsIndex, 'restockExpirePendingPayments($pdo);'),
    'developer payment detail loads lifecycle helper' => str_contains($paymentView, "includes/payment_lifecycle.php"),
    'developer payment detail syncs expired payments' => str_contains($paymentView, 'restockExpirePendingPayments($pdo);'),
];

$failed = [];
foreach ($assertions as $name => $passed) {
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, "FAILED: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "PASS: Task 15B-1 payment lifecycle contract" . PHP_EOL;
