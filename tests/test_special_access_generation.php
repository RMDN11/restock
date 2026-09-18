<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$file = $root . '/developer/index.php';

if (!is_file($file)) {
    fwrite(STDERR, "FAIL: developer index missing\n");
    exit(1);
}

$content = file_get_contents($file);

$needles = [
    "CREATE_SPECIAL_ACCESS",
    "random_bytes(32)",
    "INSERT INTO special_access_links",
    ":token_hash",
    ":expires_at",
    "PDO::PARAM_INT",
    "Buat Link Akses Tanpa Subscription",
];

foreach ($needles as $needle) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAIL: missing special access contract: {$needle}\n");
        exit(1);
    }
}

$preparePosition = strpos($content, '$insert = $pdo->prepare');
$executePosition = strpos($content, '$insert->execute', $preparePosition);
if ($preparePosition === false || $executePosition === false || $executePosition < $preparePosition) {
    fwrite(STDERR, "FAIL: special access insert order invalid\n");
    exit(1);
}

$bindBeforePrepare = strpos($content, '$insert->bindValue', 0, $preparePosition);
if ($bindBeforePrepare !== false) {
    fwrite(STDERR, "FAIL: bindValue used before insert statement is prepared\n");
    exit(1);
}

echo "PASS: special access generation contract\n";
