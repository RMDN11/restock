<?php
declare(strict_types=1);

/**
 * Static contract checks for Task 15B-2.
 * Runtime email/database tests require the production-like hosting environment.
 */

$root = dirname(__DIR__);

$checkout = file_get_contents($root . '/checkout.php');
$paymentEmail = file_get_contents($root . '/includes/payment_email.php');
$paymentView = file_get_contents($root . '/developer/payments/view.php');

$assertions = [
    'checkout has animated confirmation state' => str_contains($checkout, 'restock-success-icon'),
    'checkout confirms proof submission' => str_contains($checkout, 'Bukti pembayaran berhasil dikirim'),
    'checkout explains verification next step' => str_contains($checkout, 'subscription akan aktif otomatis'),
    'checkout explains email confirmation' => str_contains($checkout, 'Konfirmasi verifikasi dikirim ke email akun yang terdaftar'),
    'email helper exists' => $paymentEmail !== false,
    'email helper validates recipient' => str_contains($paymentEmail, 'FILTER_VALIDATE_EMAIL'),
    'email helper sends HTML email' => str_contains($paymentEmail, 'Content-Type: text/html'),
    'email helper has verification subject' => str_contains($paymentEmail, 'Pembayaran RESTOCK berhasil diverifikasi'),
    'developer verify loads email helper' => str_contains($paymentView, "includes/payment_email.php"),
    'developer verify calls email helper' => str_contains($paymentView, 'restockSendPaymentVerifiedEmail('),
    'developer detail previews image proof' => str_contains($paymentView, '$isProofImage'),
    'developer detail previews PDF proof' => str_contains($paymentView, '$isProofPdf'),
    'developer detail has full proof link' => str_contains($paymentView, 'Buka bukti penuh'),
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

echo "PASS: Task 15B-2 payment confirmation contract" . PHP_EOL;
