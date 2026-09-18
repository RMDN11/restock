<?php
$root = dirname(__DIR__);
$listFile = $root . '/developer/accounts/index.php';
$viewFile = $root . '/developer/accounts/view.php';
$headerFile = $root . '/developer/includes/header.php';
$sidebarFile = $root . '/developer/includes/sidebar.php';
$footerFile = $root . '/developer/includes/footer.php';

if (!is_file($listFile) || !is_file($viewFile) || !is_file($headerFile) || !is_file($sidebarFile) || !is_file($footerFile)) {
    fwrite(STDERR, "Developer Accounts pages and shell must exist.\n");
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
    [$list, "su2.role = 'ADMIN'", 'Accounts list must limit usernames to admin memberships.'],
    [$list, "su2.status = 'ACTIVE'", 'Accounts list must limit usernames to active memberships.'],
    [$list, "u2.status = 'ACTIVE'", 'Accounts list must limit usernames to active users.'],
    [$list, 'developer_auth.php', 'Accounts list must use developer authentication.'],
    [$list, "require_once __DIR__ . '/../includes/header.php'", 'Accounts list must use the current Developer header shell.'],
    [$list, "require_once __DIR__ . '/../includes/sidebar.php'", 'Accounts list must use the current Developer sidebar shell.'],
    [$list, "require_once __DIR__ . '/../includes/footer.php'", 'Accounts list must use the current Developer footer shell.'],
    [$list, '$pdo->prepare($sql)', 'Accounts list must prepare its query.'],
    [$view, 'FROM accounts a', 'Account detail must read the selected account.'],
    [$view, 'FROM stores s', 'Account detail must list the account stores.'],
    [$view, 'JOIN accounts a ON a.id = s.account_id', 'Account detail must scope stores to the selected account.'],
    [$view, 'FROM audit_logs al', 'Account detail must load recent account activity.'],
    [$view, 's.account_id = :account_id', 'Account activity must be scoped to the selected account.'],
    [$view, 'developer_auth.php', 'Account detail must use developer authentication.'],
    [$view, "require_once __DIR__ . '/../includes/header.php'", 'Account detail must use the current Developer header shell.'],
    [$view, "require_once __DIR__ . '/../includes/sidebar.php'", 'Account detail must use the current Developer sidebar shell.'],
    [$view, "require_once __DIR__ . '/../includes/footer.php'", 'Account detail must use the current Developer footer shell.'],
];

$failures = [];
foreach ($checks as [$content, $needle, $message]) {
    if (strpos($content, $needle) === false) {
        $failures[] = $message;
    }
}

if (substr_count($view, '$pdo->prepare(') < 3) {
    $failures[] = 'Account detail queries must use prepared statements.';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Developer accounts tests: PASS\n";
