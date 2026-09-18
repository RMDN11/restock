<?php
/*
|--------------------------------------------------------------------------
| RESTOCK - Developer Authentication Guard
|--------------------------------------------------------------------------
| Hanya user ACTIVE dengan role DEVELOPER yang dapat masuk ke Console.
| Developer tidak membutuhkan membership Store.
|--------------------------------------------------------------------------
*/

const RESTOCK_SESSION_LIFETIME = 86400;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string) RESTOCK_SESSION_LIFETIME);

    session_set_cookie_params([
        'lifetime' => RESTOCK_SESSION_LIFETIME,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once __DIR__ . '/../config/database.php';

function destroyDeveloperSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'] ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

$loginAt = (int) ($_SESSION['login_at'] ?? 0);
if ($loginAt > 0 && (time() - $loginAt) >= RESTOCK_SESSION_LIFETIME) {
    destroyDeveloperSession();
    header('Location: /login.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
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
$stmt->execute([':user_id' => $userId]);
$currentUser = $stmt->fetch();

if (!$currentUser || $currentUser['status'] !== 'ACTIVE') {
    destroyDeveloperSession();
    header('Location: /login.php');
    exit;
}

$authUser = $currentUser;
$authUserId = (int) $currentUser['id'];
$authRole = $currentUser['role'];
$authName = $currentUser['name'];
$authUsername = $currentUser['username'];

if ($authRole !== 'DEVELOPER') {
    header('Location: /');
    exit;
}

$_SESSION['user_id'] = $authUserId;
$_SESSION['user_name'] = $authName;
$_SESSION['username'] = $authUsername;
$_SESSION['role'] = 'DEVELOPER';
unset(
    $_SESSION['account_id'],
    $_SESSION['store_id'],
    $_SESSION['store_name'],
    $_SESSION['store_slug']
);
