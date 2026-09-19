<?php
$storeHelper = file_get_contents(__DIR__ . '/../includes/store_management.php');
$stores = file_get_contents(__DIR__ . '/../stores.php');
$switchStore = file_get_contents(__DIR__ . '/../switch-store.php');
$subscription = file_get_contents(__DIR__ . '/../includes/subscription.php');
$sidebar = file_get_contents(__DIR__ . '/../includes/sidebar.php');
$checkout = file_get_contents(__DIR__ . '/../checkout.php');
$migration = file_get_contents(__DIR__ . '/../database/migrations/014_payment_accounts.sql');
$paymentAccounts = file_get_contents(__DIR__ . '/../developer/payment-accounts/index.php');
$developerSidebar = file_get_contents(__DIR__ . '/../developer/includes/sidebar.php');

assert(strpos($storeHelper, 'restockGetAccountStoreEntitlement') !== false);
assert(strpos($storeHelper, "plan_type'] === 'FREE'") !== false);
assert(strpos($storeHelper, 'max_store_count') !== false);
assert(strpos($stores, 'FOR UPDATE') !== false);
assert(strpos($stores, 'store_users') !== false);
assert(strpos($stores, 'Batas') !== false);
assert(strpos($switchStore, 'AND s.account_id = :account_id') !== false);
assert(strpos($subscription, 'accountSubscription') !== false);
assert(strpos($sidebar, 'href="/stores.php"') !== false);

assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS payment_accounts') !== false);
assert(strpos($migration, 'bank_name') !== false);
assert(strpos($migration, 'account_number') !== false);
assert(strpos($paymentAccounts, 'payment_accounts') !== false);
assert(strpos($paymentAccounts, 'action" value="ADD"') !== false);
assert(strpos($developerSidebar, '/developer/payment-accounts/') !== false);
assert(strpos($checkout, 'payment_accounts') !== false);
assert(strpos($checkout, 'Rekening Pembayaran') !== false);
assert(strpos($checkout, 'copy-checkout-account') !== false);

echo "Multi-store and payment account contract passed.\n";
