<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checkout = $root . '/checkout.php';
$developerPaymentView = $root . '/developer/payments/view.php';

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assertContract(is_file($checkout), 'checkout.php harus tersedia');
assertContract(is_file($developerPaymentView), 'developer payment detail harus tersedia');

$source = file_get_contents($checkout);
$developerSource = file_get_contents($developerPaymentView);

foreach ([
    "enctype="multipart/form-data"",
    "name="proof_file"",
    "UPLOAD_PROOF",
    "move_uploaded_file",
    "is_uploaded_file",
    "finfo(FILEINFO_MIME_TYPE)",
    "image/jpeg",
    "image/png",
    "image/webp",
    "application/pdf",
    "5 * 1024 * 1024",
    "random_bytes(16)",
    "/uploads/payments/",
    "proof_file = :proof_file",
    "status = 'PENDING'",
    "account_id = :account_id",
    "store_id = :store_id",
    "hash_equals",
    "csrf_token",
    "proofStmt",
    "Bukti hanya dapat diunggah untuk pembayaran yang masih PENDING.",
] as $needle) {
    assertContract(str_contains($source, $needle), 'Kontrak upload bukti tidak ditemukan: ' . $needle);
}

assertContract(!str_contains($source, "name="amount""), 'Form checkout tidak boleh menerima amount dari client');

echo "PASS: payment proof upload contract\n";
