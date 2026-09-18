<?php
$sidebar = file_get_contents(__DIR__ . '/../developer/includes/sidebar.php');
$header = file_get_contents(__DIR__ . '/../developer/includes/header.php');
$footer = file_get_contents(__DIR__ . '/../developer/includes/footer.php');
$index = file_get_contents(__DIR__ . '/../developer/index.php');

assert(strpos($sidebar, '/assets/images/logo.png') !== false);
assert(strpos($sidebar, 'Buka Aplikasi') !== false);
assert(strpos($header, 'Muat ulang') !== false);
assert(strpos($footer, 'copySpecialAccessLink') !== false);
assert(strpos($footer, 'Tersalin') !== false);
assert(strpos($index, 'border-blue-200') !== false);
assert(strpos($index, 'Salin Link') !== false);

echo "Developer console UI tools contract passed.\n";
