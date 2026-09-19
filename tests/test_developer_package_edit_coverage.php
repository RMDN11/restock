<?php
$page = file_get_contents(__DIR__ . '/../developer/packages/edit.php');

assert($page !== false);
assert(strpos($page, 'name="duration_days"') !== false);
assert(strpos($page, 'Kelola Pricing') !== false);
assert(strpos($page, '/developer/packages/pricing.php?id=') !== false);
assert(strpos($page, 'Perubahan paket di halaman ini hanya mengubah identitas dan durasi paket.') !== false);

echo "Developer package edit identity contract passed.\n";
