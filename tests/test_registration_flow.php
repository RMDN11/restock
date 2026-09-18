<?php
$login = file_get_contents(__DIR__ . '/../login.php');
$flow = file_get_contents(__DIR__ . '/../daftar-pilih.php');
$register = file_get_contents(__DIR__ . '/../daftar.php');

assert(strpos($login, 'href="/daftar-pilih.php"') !== false);
assert(strpos($flow, 'href="/daftar.php"') !== false);
assert(strpos($flow, 'Punya undangan Free Plan?') !== false);
assert(strpos($register, 'Pilih Paket') !== false);
assert(strpos($register, 'registrationPackages') !== false);
assert(strpos($register, 'package_id=<?= (int) $package[\'id\'] ?>') !== false);
assert(strpos($register, "Pilih paket terlebih dahulu sebelum membuat akun.") === false);

echo "Registration package-first flow contract passed.\n";
