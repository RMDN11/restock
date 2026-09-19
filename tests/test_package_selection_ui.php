<?php
$canonical = file_get_contents(__DIR__ . '/../daftar-paket.php');
$legacy = file_get_contents(__DIR__ . '/../paket/index.php');

assert($canonical !== false);
assert($legacy !== false);

assert(strpos($canonical, 'min-h-[430px]') !== false);
assert(substr_count($canonical, 'mt-auto') >= 2);
assert(strpos($canonical, 'items-stretch') !== false);
assert(strpos($canonical, 'Mulai Gratis') !== false);
assert(strpos($canonical, 'Mulai dari gratis atau pilih paket sesuai jumlah toko.') !== false);
assert(strpos($canonical, '← Kembali ke pilihan pendaftaran') !== false);
assert(strpos($legacy, '/daftar-paket.php') !== false);

echo "Package selection UI contract passed.\n";
