<?php
$checkout = file_get_contents(__DIR__ . '/../checkout.php');
$renewalSelect = file_get_contents(__DIR__ . '/../renewal/select.php');

assert($checkout !== false);
assert($renewalSelect !== false);

assert(strpos($checkout, "['/daftar-paket.php', '/renewal.php']") !== false);
assert(strpos($checkout, "$_SESSION['checkout_origin']") !== false);
assert(strpos($checkout, "Kembali ke renewal") !== false);
assert(strpos($checkout, "Kembali ke pilih paket") !== false);
assert(strpos($checkout, "header('Location: ' . $checkoutOrigin);") !== false);
assert(strpos($checkout, 'href="<?= e($checkoutOrigin) ?>"') !== false);
assert(strpos($renewalSelect, "$_SESSION['checkout_origin'] = '/renewal.php';") !== false);

echo "Checkout navigation contract passed.\n";
