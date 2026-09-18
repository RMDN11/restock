<?php
declare(strict_types=1);

$path = __DIR__ . '/../developer/subscriptions/index.php';
$content = file_get_contents($path);

if ($content === false) {
    throw new RuntimeException('Cannot read developer/subscriptions/index.php');
}

if (strpos($content, "require_once __DIR__ . '/../../includes/developer_auth.php';") === false) {
    throw new RuntimeException('Subscription page must load the shared developer auth guard from the project includes directory.');
}

if (strpos($content, "require_once __DIR__ . '/../includes/developer_auth.php';") !== false) {
    throw new RuntimeException('Subscription page still contains the broken relative developer_auth.php path.');
}

echo "PASS: developer subscription auth include path is correct\n";
