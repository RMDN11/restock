<?php
$page = file_get_contents(__DIR__ . '/../developer/packages/edit.php');

assert($page !== false);
assert(strpos($page, 'name="min_store_count"') !== false);
assert(strpos($page, 'name="max_store_count"') !== false);
assert(strpos($page, '$minStoreCount') !== false);
assert(strpos($page, '$maxStoreCount') !== false);

echo "Developer package edit coverage contract passed.\n";
