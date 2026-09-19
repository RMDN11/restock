<?php
declare(strict_types=1);

/*
 * RESTOCK - Legacy Public Package Route
 *
 * Canonical registration package selection now lives at /daftar-paket.php.
 * Keep /paket/ as a compatibility route so old bookmarks and legacy links
 * never open a second, divergent package-selection UI.
 */

$allowedQuery = [];

if (isset($_GET['free_token']) && is_string($_GET['free_token'])) {
    $freeToken = trim($_GET['free_token']);

    if ($freeToken !== '') {
        $allowedQuery['free_token'] = $freeToken;
    }
}

$location = '/daftar-paket.php';

if ($allowedQuery !== []) {
    $location .= '?' . http_build_query($allowedQuery);
}

header('Location: ' . $location, true, 302);
exit;
