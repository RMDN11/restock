<?php
declare(strict_types=1);

$auth = file_get_contents(dirname(__DIR__) . '/includes/auth.php');

if (!str_contains($auth, "    '/',")) {
    fwrite(STDERR, "FAILED: root path is not subscription-gated" . PHP_EOL);
    exit(1);
}

if (!str_contains($auth, 'restockRequireApplicationAccess(')) {
    fwrite(STDERR, "FAILED: application access gate is missing" . PHP_EOL);
    exit(1);
}

echo "PASS: root dashboard access gate contract" . PHP_EOL;
