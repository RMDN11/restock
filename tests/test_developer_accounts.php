<?php
$root = dirname(__DIR__);
$listFile = $root . '/developer/accounts/index.php';
$viewFile = $root . '/developer/accounts/view.php';

if (!is_file($listFile) || !is_file($viewFile)) {
    fwrite(STDERR, "Developer Accounts pages must exist.\n");
    exit(1);
}

$list = file_get_contents($listFile);
$view = file_get_contents($viewFile);

$checks = [
    [$list, 'FROM accounts a', 'Accounts list must read accounts.'],
    [$list, 'COUNT(DISTINCT s.id)', 'Accounts list must count stores per account.'],
    [$list, 'a.status = :status', 'Accounts list must support status filtering.'],
    [$list, 'LIKE :search_name', 'Accounts list must support account search.'],
    [$list, 'LIKE :search_username', 'Accounts list must support username search.'],
    [$list, 'developer_auth.php', 'Accounts list must use developer authentication.'],
    [$view, 'FROM accounts a', 'Account detail must read the selected account.'],
    [$view, 'FROM stores s', 'Account detail must list the account stores.'],
    [$view, 'JOIN accounts a ON a.id = s.account_id', 'Account detail must scope stores to the selected account.'],
    [$view, 'FROM audit_logs al', 'Account detail must load recent account activity.'],
    [$view, 's.account_id = :account_id', 'Account activity must be scoped to the selected account.'],
    [$view, 'developer_auth.php', 'Account detail must use developer authentication.'],
];

$failures = [];
foreach ($checks as [$content, $needle, $message]) {
    if (strpos($content, $needle) === false) {
        $failures[] = $message;
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Developer accounts tests: PASS\n";
