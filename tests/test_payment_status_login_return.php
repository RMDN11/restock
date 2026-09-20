<?php
declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/payment-status.php');

if (!str_contains($page, 'href="/logout.php"')) {
    fwrite(STDERR, "FAILED: payment status does not return to login" . PHP_EOL);
    exit(1);
}

if (str_contains($page, 'Kembali ke aplikasi')) {
    fwrite(STDERR, "FAILED: old application return label remains" . PHP_EOL);
    exit(1);
}

if (!str_contains($page, 'Kembali ke login')) {
    fwrite(STDERR, "FAILED: login return label missing" . PHP_EOL);
    exit(1);
}

echo "PASS: payment status login return contract" . PHP_EOL;
