<?php
$page = file_get_contents(__DIR__ . '/../daftar.php');

assert(strpos($page, "&& !\$freeRegistration): ?>") !== false);
assert(substr_count($page, 'name="free_registration"') === 1);
assert(strpos($page, "'public-registration'") !== false);

echo "Free registration direct form contract passed.\n";
