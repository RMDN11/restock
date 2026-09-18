<?php
$selection = file_get_contents(__DIR__ . '/../daftar-pilih.php');
$packages = file_get_contents(__DIR__ . '/../daftar-paket.php');
$login = file_get_contents(__DIR__ . '/../login.php');

assert(strpos($login, 'href="/daftar-pilih.php"') !== false);
assert(strpos($selection, 'href="/daftar-paket.php"') !== false);
assert(strpos($selection, 'href="/daftar.php"') === false);
assert(strpos($packages, 'FROM packages') !== false);
assert(strpos($packages, 'href="/daftar.php?package_id=') !== false);

echo "Registration package flow contract passed.\n";
