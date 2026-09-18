<?php
$migration = file_get_contents(__DIR__ . '/../database/migrations/012_seed_registration_packages.sql');

assert(strpos($migration, "'standard'") !== false);
assert(strpos($migration, "'pro'") !== false);
assert(strpos($migration, "'business'") !== false);
assert(substr_count($migration, 'WHERE NOT EXISTS') === 3);

echo "Starter package seed contract passed.\n";
