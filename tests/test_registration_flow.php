<?php
$login = file_get_contents(__DIR__ . '/../login.php');
$flow = file_get_contents(__DIR__ . '/../daftar-pilih.php');

assert(strpos($login, 'href="/daftar-pilih.php"') !== false);
assert(strpos($login, 'href="/daftar.php"') === false);
assert(strpos($flow, 'href="/daftar.php"') !== false);
assert(strpos($flow, 'action="/free-plan.php"') !== false);
assert(strpos($flow, 'name="token"') !== false);

echo "Registration flow contract passed.\n";
