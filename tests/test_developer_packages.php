<?php
$files = [
    __DIR__ . '/../developer/packages/index.php',
    __DIR__ . '/../developer/packages/create.php',
    __DIR__ . '/../developer/packages/edit.php',
    __DIR__ . '/../developer/packages/status.php',
];

$missing = [];
foreach ($files as $file) {
    if (!is_file($file)) {
        $missing[] = $file;
    }
}

if ($missing) {
    fwrite(STDERR, "RED: expected Package Management files are missing:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}

$index = file_get_contents($files[0]);
$create = file_get_contents($files[1]);
$edit = file_get_contents($files[2]);
$status = file_get_contents($files[3]);
$all = $index . $create . $edit . $status;

$checks = [
    'developer auth' => 'developer_auth.php',
    'packages table' => 'FROM packages',
    'active filter' => "status = 'ACTIVE'",
    'create action' => 'INSERT INTO packages',
    'update action' => 'UPDATE packages',
    'status action' => 'UPDATE packages SET status',
    'csrf' => 'csrf',
    'price' => 'price',
    'duration' => 'duration_days',
    'description' => 'description',
    'slug' => 'slug',
    'prepared statements' => 'prepare(',
];

$failures = [];
foreach ($checks as $label => $needle) {
    if (strpos($all, $needle) === false) {
        $failures[] = $label;
    }
}

if ($failures) {
    fwrite(STDERR, "FAIL: " . implode(', ', $failures) . "\n");
    exit(1);
}

echo "Package Management contract: PASS\n";
