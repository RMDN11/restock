<?php
declare(strict_types=1);

$legacyFiles = [
    __DIR__ . '/../pages/pengaturan/store/index.php',
    __DIR__ . '/../pages/pengaturan/store/create.php',
    __DIR__ . '/../pages/pengaturan/store/edit.php',
    __DIR__ . '/../pages/pengaturan/store/switch.php',
];

foreach ($legacyFiles as $file) {
    assert(!is_file($file), 'Legacy duplicate store file still exists: ' . $file);
}

$storePage = file_get_contents(__DIR__ . '/../stores.php');
$switchPage = file_get_contents(__DIR__ . '/../switch-store.php');

assert(strpos($storePage, '/switch-store.php') !== false);
assert(strpos($switchPage, '/stores.php') !== false);

echo "Legacy store cleanup contract passed.\n";
