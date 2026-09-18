<?php
$root = dirname(__DIR__);
$listFile = $root . '/developer/stores/index.php';
$viewFile = $root . '/developer/stores/view.php';
$headerFile = $root . '/developer/includes/header.php';
$sidebarFile = $root . '/developer/includes/sidebar.php';
$footerFile = $root . '/developer/includes/footer.php';

if (!is_file($listFile) || !is_file($viewFile) || !is_file($headerFile) || !is_file($sidebarFile) || !is_file($footerFile)) {
    fwrite(STDERR, "Developer Stores pages and shell must exist.\n");
    exit(1);
}

$list = file_get_contents($listFile);
$view = file_get_contents($viewFile);
$sidebar = file_get_contents($sidebarFile);

$checks = [
    [$list, 'developer_auth.php', 'Stores list must use developer authentication.'],
    [$view, 'developer_auth.php', 'Store detail must use developer authentication.'],
    [$list, 'FROM stores s', 'Stores list must read stores.'],
    [$list, 'JOIN accounts a ON a.id = s.account_id', 'Stores list must resolve account ownership.'],
    [$list, 's.name LIKE :search_name', 'Stores list must support store-name search.'],
    [$list, 's.slug LIKE :search_slug', 'Stores list must support slug search.'],
    [$list, 'a.name LIKE :search_account', 'Stores list must support account search.'],
    [$list, 's.status = :status', 'Stores list must support status filtering.'],
    [$list, "su.role = 'ADMIN'", 'Stores list must identify admin membership.'],
    [$list, "su.status = 'ACTIVE'", 'Stores list must count only active memberships.'],
    [$list, "u.status = 'ACTIVE'", 'Stores list must count only active users.'],
    [$list, 'WHERE s.id = :store_id', 'Store detail must scope data to the selected store.'],
    [$view, 'FROM accounts a', 'Store detail must load the related account.'],
    [$view, 'FROM store_users su', 'Store detail must load store memberships.'],
    [$view, 'FROM audit_logs al', 'Store detail must load store activity.'],
    [$view, 'al.store_id = :store_id', 'Store activity must be scoped to the selected store.'],
    [$view, 'ORDER BY al.created_at DESC, al.id DESC', 'Store activity must show newest entries first.'],
    [$list, '$pdo->prepare($sql)', 'Stores list must use prepared statements.'],
    [$view, '$pdo->prepare(', 'Store detail must use prepared statements.'],
    [$list, "require_once __DIR__ . '/../includes/header.php'", 'Stores list must use the Developer header shell.'],
    [$list, "require_once __DIR__ . '/../includes/sidebar.php'", 'Stores list must use the Developer sidebar shell.'],
    [$list, "require_once __DIR__ . '/../includes/footer.php'", 'Stores list must use the Developer footer shell.'],
    [$view, "require_once __DIR__ . '/../includes/header.php'", 'Store detail must use the Developer header shell.'],
    [$view, "require_once __DIR__ . '/../includes/sidebar.php'", 'Store detail must use the Developer sidebar shell.'],
    [$view, "require_once __DIR__ . '/../includes/footer.php'", 'Store detail must use the Developer footer shell.'],
    [$sidebar, '/developer/stores/', 'Developer sidebar must expose Stores once the module exists.'],
];

$failures = [];
foreach ($checks as [$content, $needle, $message]) {
    if (strpos($content, $needle) === false) {
        $failures[] = $message;
    }
}

if (preg_match('/\$_POST|REQUEST_METHOD.*POST|method=["\\\']post/i', $list . $view)) {
    $failures[] = 'Developer Stores must remain read-only in Phase 2I-4.';
}

if ($failures) {
    fwrite(STDERR, "FAIL: " . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Developer stores tests: PASS\n";
