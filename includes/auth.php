<?php
/*
|--------------------------------------------------------------------------
| RESTOCK - Authentication Guard
|--------------------------------------------------------------------------
| Session login dipertahankan melalui persistent remember token.
| Cookie bertahan 30 hari dan sesi dapat dipulihkan setelah browser/app
| ditutup. Token tetap dapat dicabut melalui logout.
*/

const RESTOCK_SESSION_LIFETIME = 2592000;
const RESTOCK_REMEMBER_COOKIE = 'restock_remember';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) RESTOCK_SESSION_LIFETIME);

    session_set_cookie_params([
        'lifetime' => RESTOCK_SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/special_access.php';

function destroyRestockAuthSession(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $params['path'] ?? '/',
                'domain'   => $params['domain'] ?? '',
                'secure'   => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}

function clearRestockRememberCookie(): void {
    setcookie(
        RESTOCK_REMEMBER_COOKIE,
        '',
        [
            'expires'  => time() - 42000,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

function restoreRestockRememberedSession(PDO $pdo): bool {
    $rawToken = trim((string) ($_COOKIE[RESTOCK_REMEMBER_COOKIE] ?? ''));

    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
        return false;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare(
        "SELECT
            rt.id AS token_id,
            rt.user_id,
            u.id,
            u.name,
            u.username,
            u.role,
            u.status
         FROM remember_tokens rt
         INNER JOIN users u ON u.id = rt.user_id
         WHERE rt.token_hash = :token_hash
           AND rt.expires_at > NOW()
           AND u.status = 'ACTIVE'
         LIMIT 1"
    );
    $stmt->execute([':token_hash' => $tokenHash]);
    $user = $stmt->fetch();

    if (!$user) {
        clearRestockRememberCookie();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['login_at'] = time();
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    if ($user['role'] === 'DEVELOPER') {
        return true;
    }

    $m = $pdo->prepare(
        "SELECT su.store_id, su.role, s.name AS store_name, s.slug, s.account_id
         FROM store_users su
         INNER JOIN stores s ON s.id = su.store_id
         INNER JOIN accounts a ON a.id = s.account_id
         WHERE su.user_id = :user_id
           AND su.status = 'ACTIVE'
           AND s.status = 'ACTIVE'
           AND a.status = 'ACTIVE'
         ORDER BY su.id ASC
         LIMIT 1"
    );
    $m->execute([':user_id' => (int) $user['user_id']]);
    $membership = $m->fetch();

    if (!$membership) {
        return false;
    }

    $_SESSION['role'] = $membership['role'];
    $_SESSION['account_id'] = (int) $membership['account_id'];
    $_SESSION['store_id'] = (int) $membership['store_id'];
    $_SESSION['store_name'] = $membership['store_name'];
    $_SESSION['store_slug'] = $membership['slug'];

    return true;
}

$loginAt = (int) ($_SESSION['login_at'] ?? 0);

if (
    $loginAt > 0 &&
    (time() - $loginAt) >= RESTOCK_SESSION_LIFETIME
) {
    destroyRestockAuthSession();
    header('Location: /login.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0 && restoreRestockRememberedSession($pdo)) {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
}

if ($userId <= 0) {
    header('Location: /login.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, name, username, role, status
     FROM users
     WHERE id = :user_id
     LIMIT 1"
);

$stmt->execute([
    ':user_id' => $userId,
]);

$currentUser = $stmt->fetch();

if (
    !$currentUser ||
    $currentUser['status'] !== 'ACTIVE'
) {
    destroyRestockAuthSession();
    header('Location: /login.php');
    exit;
}

if ($currentUser['role'] === 'DEVELOPER') {
    $_SESSION['user_id']   = (int) $currentUser['id'];
    $_SESSION['user_name'] = $currentUser['name'];
    $_SESSION['username']  = $currentUser['username'];
    $_SESSION['role']      = 'DEVELOPER';
    unset(
        $_SESSION['account_id'],
        $_SESSION['store_id'],
        $_SESSION['store_name'],
        $_SESSION['store_slug']
    );

    header('Location: /developer/');
    exit;
}

$sessionStoreId = (int) ($_SESSION['store_id'] ?? 0);
$membership = null;

if ($sessionStoreId > 0) {
    $m = $pdo->prepare(
        "SELECT
            su.store_id,
            su.role,
            su.status,
            s.name AS store_name,
            s.slug,
            s.account_id,
            s.status AS store_status,
            a.status AS account_status,
            a.plan_type,
            a.free_plan_expires_at
         FROM store_users su
         INNER JOIN stores s ON s.id = su.store_id
         INNER JOIN accounts a ON a.id = s.account_id
         WHERE su.user_id = :user_id
           AND su.store_id = :store_id
           AND su.status = 'ACTIVE'
           AND s.status = 'ACTIVE'
           AND a.status = 'ACTIVE'
         LIMIT 1"
    );

    $m->execute([
        ':user_id'  => $userId,
        ':store_id' => $sessionStoreId,
    ]);

    $membership = $m->fetch();
}

if (!$membership) {
    $m = $pdo->prepare(
        "SELECT
            su.store_id,
            su.role,
            su.status,
            s.name AS store_name,
            s.slug,
            s.account_id,
            s.status AS store_status,
            a.status AS account_status,
            a.plan_type,
            a.free_plan_expires_at
         FROM store_users su
         INNER JOIN stores s ON s.id = su.store_id
         INNER JOIN accounts a ON a.id = s.account_id
         WHERE su.user_id = :user_id
           AND su.status = 'ACTIVE'
           AND s.status = 'ACTIVE'
           AND a.status = 'ACTIVE'
         ORDER BY su.id ASC
         LIMIT 1"
    );

    $m->execute([
        ':user_id' => $userId,
    ]);

    $membership = $m->fetch();
}

if (!$membership) {
    destroyRestockAuthSession();
    header('Location: /login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Subscription access gate
|--------------------------------------------------------------------------
| Hanya area aplikasi utama yang wajib punya subscription ACTIVE.
| Account dengan FREE plan aktif atau special access juga dapat masuk.
|--------------------------------------------------------------------------
*/
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$subscriptionGatedPrefixes = [
    '/pages/',
    '/index.php',
    '/index',
];

$isSubscriptionGated = false;
foreach ($subscriptionGatedPrefixes as $prefix) {
    if ($prefix === '/pages/' && str_starts_with($requestPath, $prefix)) {
        $isSubscriptionGated = true;
        break;
    }

    if ($requestPath === $prefix) {
        $isSubscriptionGated = true;
        break;
    }
}

if ($isSubscriptionGated) {
    $isFreePlan = (
        ($membership['plan_type'] ?? 'PAID') === 'FREE' &&
        (
            empty($membership['free_plan_expires_at']) ||
            strtotime((string) $membership['free_plan_expires_at']) > time()
        )
    );

    $hasSpecialAccess = $isFreePlan || restockHasSpecialAccess(
        $pdo,
        $userId,
        (int) $membership['account_id'],
        (int) $membership['store_id']
    );

    if (!$hasSpecialAccess) {
        restockRequireActiveSubscription(
            $pdo,
            (int) $membership['account_id'],
            (int) $membership['store_id']
        );
    }
}

$_SESSION['user_id']    = (int) $currentUser['id'];
$_SESSION['user_name']  = $currentUser['name'];
$_SESSION['username']   = $currentUser['username'];
$_SESSION['role']       = $membership['role'];
$_SESSION['account_id'] = (int) $membership['account_id'];
$_SESSION['store_id']   = (int) $membership['store_id'];
$_SESSION['store_name'] = $membership['store_name'];
$_SESSION['store_slug'] = $membership['slug'];

$authUser       = $currentUser;
$authUserId     = (int) $currentUser['id'];
$authRole       = $membership['role'];
$authName       = $currentUser['name'];
$authUsername   = $currentUser['username'];
$authAccountId  = (int) $membership['account_id'];
$authStoreId    = (int) $membership['store_id'];
$authStoreName  = $membership['store_name'];
$authStoreSlug  = $membership['slug'];
