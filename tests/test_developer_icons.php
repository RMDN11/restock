<?php
$header = file_get_contents(__DIR__ . '/../developer/includes/header.php');
$footer = file_get_contents(__DIR__ . '/../developer/includes/footer.php');
if ($header === false || $footer === false) {
    fwrite(STDERR, "Gagal membaca Developer shell saat ini.\n");
    exit(1);
}

if (!preg_match('/lucide\.createIcons\s*\(\s*\)/', $footer)) {
    fwrite(STDERR, "FAIL: developer/includes/footer.php belum menginisialisasi Lucide createIcons().\n");
    exit(1);
}

if (!preg_match('/developerMenuToggle/', $header) || !preg_match('/developerSidebar/', $footer)) {
    fwrite(STDERR, "FAIL: shell Developer tidak memuat kontrol sidebar saat ini.\n");
    exit(1);
}

echo "PASS: Developer Lucide icon initialization ditemukan.\n";
