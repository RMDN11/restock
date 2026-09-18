<?php
$header = file_get_contents(__DIR__ . '/../developer/header.php');
if ($header === false) {
    fwrite(STDERR, "Gagal membaca developer/header.php\n");
    exit(1);
}

if (!preg_match('/lucide\.createIcons\s*\(\s*\)/', $header)) {
    fwrite(STDERR, "FAIL: developer/header.php belum menginisialisasi Lucide createIcons().\n");
    exit(1);
}

if (!preg_match('/DOMContentLoaded/', $header)) {
    fwrite(STDERR, "FAIL: inisialisasi icon tidak menunggu DOM selesai.\n");
    exit(1);
}

echo "PASS: Developer Lucide icon initialization ditemukan.\n";
