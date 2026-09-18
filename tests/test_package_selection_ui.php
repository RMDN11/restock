<?php
$page = file_get_contents(__DIR__ . '/../daftar-paket.php');

assert(strpos($page, 'min-h-[430px]') !== false);
assert(substr_count($page, 'mt-auto') >= 2);
assert(strpos($page, 'items-stretch') !== false);
assert(strpos($page, 'Mulai tanpa bayar') !== false);
assert(strpos($page, 'Mulai dari gratis atau pilih paket sesuai jumlah toko.') !== false);

echo "Package selection UI contract passed.\n";
