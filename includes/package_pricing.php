<?php
declare(strict_types=1);

/*
 * RESTOCK - Package Pricing
 *
 * The package table defines the product. package_price_tiers defines the
 * price and included store capacity selected at checkout.
 */

function restockGetPackageTiers(PDO $pdo, int $packageId, bool $activeOnly = true): array
{
    if ($packageId <= 0) {
        return [];
    }

    $statusSql = $activeOnly ? "AND status = 'ACTIVE'" : '';

    $stmt = $pdo->prepare(
        "SELECT id, package_id, min_store_count, max_store_count, price, status, sort_order
         FROM package_price_tiers
         WHERE package_id = :package_id
           {$statusSql}
         ORDER BY min_store_count ASC, sort_order ASC, id ASC"
    );
    $stmt->execute([':package_id' => $packageId]);

    return $stmt->fetchAll();
}

function restockFindPackagePriceTier(PDO $pdo, int $packageId, int $storeCount): ?array
{
    if ($packageId <= 0 || $storeCount < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, package_id, min_store_count, max_store_count, price, status, sort_order
         FROM package_price_tiers
         WHERE package_id = :package_id
           AND status = 'ACTIVE'
           AND min_store_count <= :store_count_min
           AND (max_store_count IS NULL OR max_store_count >= :store_count_max)
         ORDER BY min_store_count DESC, sort_order ASC, id DESC
         LIMIT 1"
    );
    $stmt->execute([
        ':package_id' => $packageId,
        ':store_count_min' => $storeCount,
        ':store_count_max' => $storeCount,
    ]);

    $tier = $stmt->fetch();

    return $tier ?: null;
}

function restockGetPackageWithTiers(PDO $pdo, int $packageId): ?array
{
    if ($packageId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, slug, price, duration_days, min_store_count, max_store_count, description, status
         FROM packages
         WHERE id = :package_id
         LIMIT 1"
    );
    $stmt->execute([':package_id' => $packageId]);
    $package = $stmt->fetch();

    if (!$package) {
        return null;
    }

    $package['tiers'] = restockGetPackageTiers($pdo, $packageId);

    return $package;
}

function restockGetPackageStartingPrice(array $package): float
{
    if (!empty($package['tiers'])) {
        $prices = array_map(
            static fn (array $tier): float => (float) $tier['price'],
            $package['tiers']
        );

        return min($prices);
    }

    return (float) $package['price'];
}

function restockGetPackageDefaultStoreCount(array $package): int
{
    if (!empty($package['tiers'])) {
        return max(1, (int) $package['tiers'][0]['min_store_count']);
    }

    return max(1, (int) ($package['min_store_count'] ?? 1));
}

function restockFormatStoreRange(?int $minStoreCount, ?int $maxStoreCount): string
{
    if ($minStoreCount === null && $maxStoreCount === null) {
        return 'Jumlah toko fleksibel';
    }

    $min = max(1, (int) ($minStoreCount ?? 1));

    if ($maxStoreCount === null) {
        return $min . '+ toko';
    }

    $max = max($min, (int) $maxStoreCount);

    return $min === $max
        ? $min . ' toko'
        : $min . '–' . $max . ' toko';
}

function restockValidateTierCoverage(PDO $pdo, int $packageId, ?int $excludeTierId, int $minStoreCount, ?int $maxStoreCount): bool
{
    if ($packageId <= 0 || $minStoreCount < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id, min_store_count, max_store_count
         FROM package_price_tiers
         WHERE package_id = :package_id
           AND status = 'ACTIVE'
           AND (:exclude_tier_id_a IS NULL OR id <> :exclude_tier_id_b)"
    );
    $stmt->execute([
        ':package_id' => $packageId,
        ':exclude_tier_id_a' => $excludeTierId,
        ':exclude_tier_id_b' => $excludeTierId,
    ]);

    foreach ($stmt->fetchAll() as $tier) {
        $existingMin = (int) $tier['min_store_count'];
        $existingMax = $tier['max_store_count'] !== null ? (int) $tier['max_store_count'] : PHP_INT_MAX;
        $newMax = $maxStoreCount !== null ? $maxStoreCount : PHP_INT_MAX;

        if ($minStoreCount <= $existingMax && $existingMin <= $newMax) {
            return false;
        }
    }

    return true;
}
