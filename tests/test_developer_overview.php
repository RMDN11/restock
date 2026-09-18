<?php
/**
 * Lightweight static regression test for Developer Overview.
 * Run: php tests/test_developer_overview.php
 */

$path = __DIR__ . '/../developer/index.php';
$content = file_get_contents($path);
if ($content === false) {
    fwrite(STDERR, "FAIL: developer/index.php tidak dapat dibaca.\n");
    exit(1);
}

$checks = [
    'account count' => "developerCount(\$pdo, 'accounts')",
    'store count' => "developerCount(\$pdo, 'stores')",
    'active account count' => "developerCount(\$pdo, 'accounts', 'ACTIVE')",
    'active store count' => "developerCount(\$pdo, 'stores', 'ACTIVE')",
    'recent audit query' => 'FROM audit_logs',
    'copy button' => 'navigator.clipboard.writeText',
    'copy feedback' => 'Copied',
];

$failures = [];
foreach ($checks as $label => $needle) {
    if (strpos($content, $needle) === false) {
        $failures[] = $label;
    }
}

if ($failures) {
    fwrite(STDERR, "FAIL: fitur belum ditemukan: " . implode(', ', $failures) . "\n");
    exit(1);
}

if (preg_match('/\$_SESSION\[[\'\"]store_id[\'\"]\]/', $content)) {
    fwrite(STDERR, "FAIL: Developer Overview tidak boleh bergantung pada store_id session.\n");
    exit(1);
}

echo "PASS: Developer Overview static regression checks.\n";
