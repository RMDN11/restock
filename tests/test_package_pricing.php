<?php
declare(strict_types=1);

$files = [
    __DIR__ . '/../database/migrations/015_tiered_package_pricing.sql',
    __DIR__ . '/../includes/package_pricing.php',
    __DIR__ . '/../daftar-paket.php',
    __DIR__ . '/../daftar.php',
    __DIR__ . '/../checkout.php',
    __DIR__ . '/../renewal.php',
    __DIR__ . '/../renewal/select.php',
    __DIR__ . '/../developer/packages/index.php',
    __DIR__ . '/../developer/packages/create.php',
    __DIR__ . '/../developer/packages/pricing.php',
    __DIR__ . '/../developer/payments/view.php',
    __DIR__ . '/../includes/store_management.php',
];

foreach ($files as $file) {
    assert(is_file($file), 'Missing pricing file: ' . $file);
}

$migration = file_get_contents($files[0]);
$helper = file_get_contents($files[1]);
$selection = file_get_contents($files[2]);
$registration = file_get_contents($files[3]);
$checkout = file_get_contents($files[4]);
$renewal = file_get_contents($files[5]);
$renewalSelect = file_get_contents($files[6]);
$developerIndex = file_get_contents($files[7]);
$developerCreate = file_get_contents($files[8]);
$developerPricing = file_get_contents($files[9]);
$paymentView = file_get_contents($files[10]);
$storeManagement = file_get_contents($files[11]);

assert(strpos($migration, 'CREATE TABLE IF NOT EXISTS package_price_tiers') !== false);
assert(strpos($migration, 'pricing_tier_id') !== false);
assert(strpos($migration, 'store_count') !== false);
assert(strpos($migration, 'INSERT INTO package_price_tiers') !== false);

assert(strpos($helper, 'restockFindPackagePriceTier') !== false);
assert(strpos($helper, 'restockValidateTierCoverage') !== false);

assert(strpos($selection, 'restockGetPackageTiers') !== false);
assert(strpos($selection, 'name="store_count"') !== false);
assert(strpos($selection, 'package_price_tiers') !== false);

assert(strpos($registration, '$_SESSION['selected_store_count']') !== false);
assert(strpos($registration, 'restockFindPackagePriceTier') !== false);

assert(strpos($checkout, 'pricing_tier_id') !== false);
assert(strpos($checkout, 'store_count') !== false);
assert(strpos($checkout, ':amount' => $pricingTier['price']') !== false);

assert(strpos($renewal, 'restockGetPackageTiers') !== false);
assert(strpos($renewal, 'name="store_count"') !== false);
assert(strpos($renewalSelect, 'restockFindPackagePriceTier') !== false);

assert(strpos($developerIndex, '/developer/packages/pricing.php?id=') !== false);
assert(strpos($developerCreate, 'INSERT INTO package_price_tiers') !== false);
assert(strpos($developerPricing, 'Rentang toko bertabrakan') !== false);

assert(strpos($paymentView, 'pricing_tier_id') !== false);
assert(strpos($paymentView, 'store_count') !== false);
assert(strpos($storeManagement, 'purchased_store_count') !== false);

echo "Task 14 tiered pricing contract passed.\n";
