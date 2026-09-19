<?php
$register = file_get_contents(__DIR__ . '/../daftar.php');

assert(strpos($register, "if (\$_SERVER['REQUEST_METHOD'] === 'GET' && \$packageId === null && \$freePlanToken === '' && !\$freeRegistration)") !== false);
assert(strpos($register, "header('Location: /daftar-paket.php');") !== false);
assert(strpos($register, "\$_SESSION['selected_package_id'] = \$packageId;") !== false);
assert(strpos($register, "\$_SESSION['checkout_origin'] = '/daftar-paket.php';") !== false);
assert(strpos($register, "header('Location: /checkout.php');") !== false);

echo "Registration package-first flow contract passed.\n";
