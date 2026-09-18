<?php
$register = file_get_contents(__DIR__ . '/../daftar.php');

assert(strpos($register, 'Pilih Paket') !== false);
assert(strpos($register, 'registrationPackages') !== false);
assert(strpos($register, 'package_id=<?= (int) $package[\'id\'] ?>') !== false);
assert(strpos($register, "Pilih paket terlebih dahulu sebelum membuat akun.") === false);

echo "Registration package-first flow contract passed.\n";
