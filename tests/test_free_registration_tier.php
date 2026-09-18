<?php
$packages = file_get_contents(__DIR__ . '/../daftar-paket.php');
$registration = file_get_contents(__DIR__ . '/../daftar.php');
$migration = file_get_contents(__DIR__ . '/../database/migrations/013_standard_store_tier.sql');

assert(strpos($packages, "Gratis") !== false);
assert(strpos($packages, "1 owner · 1 toko") !== false);
assert(strpos($packages, "/daftar.php?free=1") !== false);
assert(strpos($registration, '$freeRegistration') !== false);
assert(strpos($registration, "'plan_type' =>") !== false);
assert(strpos($registration, "'public-registration'") !== false);
assert(strpos($registration, "header('Location: /?free_plan=activated')") !== false);
assert(strpos($migration, "min_store_count = 2") !== false);
assert(strpos($migration, "max_store_count = 3") !== false);

echo "Free registration tier contract passed.\n";
