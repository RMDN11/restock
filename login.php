<?php
/*
|--------------------------------------------------------------------------
| RESTOCK - Persistent Login Session
|--------------------------------------------------------------------------
| Login dipertahankan melalui remember token selama 30 hari.
| Browser/app dapat ditutup tanpa memaksa user login ulang.
*/

const RESTOCK_SESSION_LIFETIME = 2592000; // 30 hari
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

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/free_plan.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function destroyRestockSession(): void {
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

function establishStoreSession(PDO $pdo, array $user): bool {
    $existingStoreId = (int) ($_SESSION['store_id'] ?? 0);
    $membership = null;

    if ($existingStoreId > 0) {
        $m = $pdo->prepare(
            "SELECT
                su.store_id,
                su.role,
                s.name AS store_name,
                s.slug,
                s.account_id
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
            ':user_id'  => $user['id'],
            ':store_id' => $existingStoreId,
        ]);

        $membership = $m->fetch();
    }

    if (!$membership) {
        $m = $pdo->prepare(
            "SELECT
                su.store_id,
                su.role,
                s.name AS store_name,
                s.slug,
                s.account_id
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
            ':user_id' => $user['id'],
        ]);

        $membership = $m->fetch();
    }

    if (!$membership) {
        return false;
    }

    $_SESSION['user_id']     = (int) $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['role']        = $membership['role'];
    $_SESSION['account_id']  = (int) $membership['account_id'];
    $_SESSION['store_id']    = (int) $membership['store_id'];
    $_SESSION['store_name']  = $membership['store_name'];
    $_SESSION['store_slug']  = $membership['slug'];

    /*
     * Hanya diisi saat autentikasi berhasil.
     * Jangan diperbarui setiap request agar 24 jam benar-benar
     * dihitung dari waktu login, bukan menjadi session tanpa batas.
     */
    if (empty($_SESSION['login_at'])) {
        $_SESSION['login_at'] = time();
    }

    return true;
}

function issueRestockRememberToken(PDO $pdo, int $userId): void {
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare(
        "INSERT INTO remember_tokens
            (user_id, token_hash, expires_at, created_at)
         VALUES
            (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())"
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':token_hash' => $tokenHash,
    ]);

    setcookie(
        RESTOCK_REMEMBER_COOKIE,
        $rawToken,
        [
            'expires' => time() + RESTOCK_SESSION_LIFETIME,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

function restockSessionExpired(): bool {
    $loginAt = (int) ($_SESSION['login_at'] ?? 0);

    if ($loginAt <= 0) {
        return false;
    }

    return (time() - $loginAt) >= RESTOCK_SESSION_LIFETIME;
}

/*
|--------------------------------------------------------------------------
| Jika sudah login
|--------------------------------------------------------------------------
*/

$existingUserId = (int) ($_SESSION['user_id'] ?? 0);

if ($existingUserId > 0) {

    if (restockSessionExpired()) {
        destroyRestockSession();
        session_set_cookie_params([
            'lifetime' => RESTOCK_SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, name, username, password, role, status
             FROM users
             WHERE id = :user_id
             LIMIT 1"
        );

        $stmt->execute([
            ':user_id' => $existingUserId,
        ]);

        $existingUser = $stmt->fetch();

        if (
            $existingUser &&
            $existingUser['status'] === 'ACTIVE'
        ) {
            if ($existingUser['role'] === 'DEVELOPER') {
                $_SESSION['user_id']   = (int) $existingUser['id'];
                $_SESSION['user_name'] = $existingUser['name'];
                $_SESSION['username']  = $existingUser['username'];
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

            if (establishStoreSession($pdo, $existingUser)) {
                header('Location: /');
                exit;
            }
        }

        destroyRestockSession();

        session_set_cookie_params([
            'lifetime' => RESTOCK_SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

$error = '';

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Username dan password wajib diisi.';

    } else {

        $stmt = $pdo->prepare(
            "SELECT id, name, username, password, role, status
             FROM users
             WHERE username = :username
             LIMIT 1"
        );

        $stmt->execute([
            ':username' => $username,
        ]);

        $user = $stmt->fetch();

        if (
            $user &&
            $user['status'] === 'ACTIVE' &&
            password_verify($password, $user['password'])
        ) {

            session_regenerate_id(true);

            /*
             * Mulai ulang hitungan 24 jam hanya ketika benar-benar
             * berhasil login dengan username + password.
             */
            $_SESSION['login_at'] = time();

            if ($user['role'] === 'DEVELOPER') {
                $_SESSION['user_id']   = (int) $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['username']  = $user['username'];
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

            if (establishStoreSession($pdo, $user)) {
                $pendingFreePlanToken = trim((string) ($_SESSION['pending_free_plan_token'] ?? ''));
                unset($_SESSION['pending_free_plan_token']);

                if ($pendingFreePlanToken !== '') {
                    try {
                        $pdo->beginTransaction();
                        $freePlanResult = restockFreePlanRedeem(
                            $pdo,
                            $pendingFreePlanToken,
                            (int) $_SESSION['account_id']
                        );

                        if (!$freePlanResult['success']) {
                            $pdo->rollBack();
                            $error = $freePlanResult['message'];
                        } else {
                            $pdo->commit();
                            header('Location: /?free_plan=activated');
                            exit;
                        }
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = 'Free Plan belum dapat diaktifkan. Silakan coba lagi.';
                    }
                } else {
                    $pendingSpecialAccessToken = trim((string) ($_SESSION['pending_special_access_token'] ?? ''));
                    unset($_SESSION['pending_special_access_token']);

                    if ($pendingSpecialAccessToken !== '') {
                        header('Location: /developer-access.php?token=' . rawurlencode($pendingSpecialAccessToken));
                        exit;
                    }

                    header('Location: /');
                    exit;
                }
            }

            if ($error === '') {
                $error = 'Akun belum terhubung ke Store aktif.';
            }

        } else {

            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#f5f5f5"
    >

    <title>
        Masuk · RE-STOCK
    </title>

    <link
        rel="icon"
        type="image/svg+xml"
        href="/assets/images/logo.png"
    >

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        rel="stylesheet"
        href="/assets/css/app.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(255,255,255,.95),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 90% 90%,
                    rgba(229,229,229,.8),
                    transparent 35%
                ),
                #f5f5f5;

            color: #171717;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            -webkit-font-smoothing: antialiased;
        }


        /* =====================================================
           BACKGROUND
        ====================================================== */

        .login-background {
            position: fixed;
            inset: 0;

            overflow: hidden;

            pointer-events: none;
        }

        .login-grid {
            position: absolute;
            inset: 0;

            opacity: .45;

            background-image:
                linear-gradient(
                    rgba(0,0,0,.025) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(0,0,0,.025) 1px,
                    transparent 1px
                );

            background-size: 32px 32px;

            mask-image:
                linear-gradient(
                    to bottom,
                    black,
                    transparent 75%
                );
        }


        .orb {
            position: absolute;

            border-radius: 999px;

            filter: blur(1px);

            opacity: .55;

            animation:
                floatOrb
                8s
                ease-in-out
                infinite;
        }


        .orb-one {
            width: 280px;
            height: 280px;

            top: -120px;
            left: -100px;

            background:
                radial-gradient(
                    circle,
                    rgba(255,255,255,.9),
                    rgba(255,255,255,0)
                );
        }


        .orb-two {
            width: 360px;
            height: 360px;

            right: -160px;
            bottom: -160px;

            background:
                radial-gradient(
                    circle,
                    rgba(212,212,212,.55),
                    rgba(212,212,212,0)
                );

            animation-delay: -3s;
        }


        @keyframes floatOrb {

            0%,
            100% {
                transform:
                    translate3d(0,0,0)
                    scale(1);
            }

            50% {
                transform:
                    translate3d(0,-16px,0)
                    scale(1.04);
            }

        }


        /* =====================================================
           MAIN
        ====================================================== */

        .login-wrapper {
            position: relative;

            min-height: 100vh;

            display: flex;

            align-items: center;
            justify-content: center;

            padding: 24px;
        }


        .login-container {
            width: 100%;

            max-width: 1120px;

            display: grid;

            grid-template-columns:
                minmax(0, 1.1fr)
                minmax(360px, .9fr);

            overflow: hidden;

            border:
                1px solid
                rgba(212,212,212,.9);

            border-radius: 32px;

            background:
                rgba(255,255,255,.72);

            box-shadow:
                0 30px 80px
                rgba(0,0,0,.08);

            backdrop-filter:
                blur(20px);

            -webkit-backdrop-filter:
                blur(20px);

            animation:
                containerIn
                .65s
                cubic-bezier(.22,1,.36,1)
                both;
        }


        @keyframes containerIn {

            from {
                opacity: 0;

                transform:
                    translateY(18px)
                    scale(.985);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0)
                    scale(1);
            }

        }


        /* =====================================================
           DESKTOP TOOLS
        ====================================================== */

        .tools-panel {
            position: relative;

            min-height: 620px;

            padding: 48px;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            overflow: hidden;

            background:
                linear-gradient(
                    145deg,
                    #fafafa 0%,
                    #eeeeee 100%
                );

            border-right:
                1px solid
                #e5e5e5;
        }


        .tools-panel::after {
            content: "";

            position: absolute;

            width: 280px;
            height: 280px;

            right: -120px;
            top: -120px;

            border-radius: 50%;

            border:
                1px solid
                rgba(0,0,0,.06);

            box-shadow:
                0 0 0 45px rgba(0,0,0,.015),
                0 0 0 90px rgba(0,0,0,.01);
        }


        .tools-brand {
            position: relative;
            z-index: 2;
        }


        .mini-logo {
            width: 44px;
            height: 44px;

            border-radius: 13px;

            overflow: hidden;

            background: #171717;

            box-shadow:
                0 8px 20px
                rgba(0,0,0,.12);
        }


        .mini-logo img {
            width: 100%;
            height: 100%;

            object-fit: cover;
        }


        .tools-eyebrow {
            margin-top: 24px;

            font-size: 11px;
            font-weight: 700;

            letter-spacing: .14em;

            color: #737373;
        }


        .tools-title {
            margin-top: 8px;

            max-width: 430px;

            font-size: 34px;
            line-height: 1.1;

            font-weight: 700;

            letter-spacing: -.04em;
        }


        .tools-description {
            margin-top: 14px;

            max-width: 400px;

            color: #737373;

            font-size: 14px;

            line-height: 1.7;
        }


        .tools-list {
            position: relative;

            z-index: 2;

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 10px;

            margin-top: 38px;
        }


        .tool-item {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 13px;

            border:
                1px solid
                rgba(212,212,212,.9);

            border-radius: 15px;

            background:
                rgba(255,255,255,.7);

            transition:
                transform .2s ease,
                background .2s ease,
                border-color .2s ease;
        }


        .tool-item:hover {
            transform: translateY(-3px);

            background: #ffffff;

            border-color: #cfcfcf;
        }


        .tool-icon {
            width: 34px;
            height: 34px;

            flex: 0 0 auto;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 10px;

            background: #171717;

            color: #ffffff;
        }


        .tool-icon svg {
            width: 16px;
            height: 16px;
        }


        .tool-name {
            font-size: 12px;

            font-weight: 600;
        }


        .tool-caption {
            margin-top: 2px;

            font-size: 10px;

            color: #a3a3a3;
        }


        .tools-footer {
            position: relative;

            z-index: 2;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid
                rgba(212,212,212,.8);

            color: #a3a3a3;

            font-size: 11px;
        }


        /* =====================================================
           LOGIN PANEL
        ====================================================== */

        .login-panel {
            min-height: 620px;

            display: flex;

            align-items: center;
            justify-content: center;

            padding: 48px;
        }


        .login-content {
            width: 100%;

            max-width: 390px;

            animation:
                loginIn
                .7s
                .1s
                cubic-bezier(.22,1,.36,1)
                both;
        }


        @keyframes loginIn {

            from {
                opacity: 0;

                transform:
                    translateY(12px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }

        }


        .mobile-brand {
            display: none;
        }


        .login-logo {
            width: 58px;
            height: 58px;

            border-radius: 17px;

            overflow: hidden;

            background: #171717;

            box-shadow:
                0 12px 28px
                rgba(0,0,0,.14);
        }


        .login-logo img {
            width: 100%;
            height: 100%;

            object-fit: cover;
        }


        .login-heading {
            margin-top: 26px;
        }


        .login-heading h1 {
            margin: 0;

            font-size: 28px;

            line-height: 1.15;

            font-weight: 700;

            letter-spacing: -.04em;
        }


        .login-heading p {
            margin: 8px 0 0;

            color: #737373;

            font-size: 13px;
        }


        /* =====================================================
           ERROR
        ====================================================== */

        .login-error {
            margin-top: 22px;

            padding: 12px 14px;

            display: flex;

            align-items: flex-start;

            gap: 10px;

            border:
                1px solid
                #fecaca;

            border-radius: 13px;

            background: #fef2f2;

            color: #dc2626;

            font-size: 12px;

            animation:
                errorIn
                .3s
                ease
                both;
        }


        .login-error svg {
            width: 16px;
            height: 16px;

            flex: 0 0 auto;

            margin-top: 1px;
        }


        @keyframes errorIn {

            from {
                opacity: 0;

                transform:
                    translateY(-5px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }

        }


        /* =====================================================
           FORM
        ====================================================== */

        .login-form {
            margin-top: 28px;
        }


        .field {
            margin-bottom: 17px;
        }


        .field-label {
            display: block;

            margin-bottom: 8px;

            color: #404040;

            font-size: 12px;

            font-weight: 600;
        }


        .input-wrapper {
            position: relative;
        }


        .input-icon {
            position: absolute;

            left: 14px;
            top: 50%;

            transform:
                translateY(-50%);

            color: #a3a3a3;

            pointer-events: none;
        }


        .input-icon svg {
            width: 17px;
            height: 17px;
        }


        .login-input {
            width: 100%;

            height: 48px;

            padding:
                0 45px;

            border:
                1px solid
                #e5e5e5;

            border-radius: 14px;

            outline: none;

            background:
                rgba(255,255,255,.9);

            color: #171717;

            font-size: 13px;

            transition:
                border-color .2s ease,
                box-shadow .2s ease,
                background .2s ease;
        }


        .login-input::placeholder {
            color: #b5b5b5;
        }


        .login-input:hover {
            border-color: #d4d4d4;
        }


        .login-input:focus {
            border-color: #a3a3a3;

            background: #ffffff;

            box-shadow:
                0 0 0 4px
                rgba(0,0,0,.04);
        }


        .password-toggle {
            position: absolute;

            right: 11px;
            top: 50%;

            transform:
                translateY(-50%);

            width: 32px;
            height: 32px;

            display: flex;

            align-items: center;
            justify-content: center;

            border: 0;

            border-radius: 9px;

            background: transparent;

            color: #a3a3a3;
        }


        .password-toggle:hover {
            background: #f5f5f5;

            color: #525252;
        }


        .password-toggle svg {
            width: 16px;
            height: 16px;
        }


        /* =====================================================
           BUTTON
        ====================================================== */

        .login-button {
            width: 100%;

            height: 49px;

            margin-top: 6px;

            display: flex;

            align-items: center;
            justify-content: center;

            gap: 9px;

            border: 0;

            border-radius: 14px;

            background: #171717;

            color: #ffffff;

            font-size: 13px;

            font-weight: 600;

            box-shadow:
                0 10px 24px
                rgba(0,0,0,.12);

            transition:
                transform .18s ease,
                background .18s ease,
                box-shadow .18s ease;
        }


        .login-button:hover {
            background: #262626;

            transform:
                translateY(-1px);

            box-shadow:
                0 14px 28px
                rgba(0,0,0,.14);
        }


        .login-button:active {
            transform:
                translateY(0)
                scale(.99);
        }


        .login-button.loading {
            pointer-events: none;

            opacity: .8;
        }


        .spinner {
            width: 16px;
            height: 16px;

            border:
                2px solid
                rgba(255,255,255,.35);

            border-top-color:
                #ffffff;

            border-radius: 50%;

            animation:
                spin
                .7s
                linear
                infinite;
        }


        @keyframes spin {

            to {
                transform:
                    rotate(360deg);
            }

        }


        .login-footer {
            margin-top: 28px;

            color: #a3a3a3;

            font-size: 10px;

            text-align: center;
        }


        /* =====================================================
           MOBILE
        ====================================================== */

        @media (max-width: 900px) {

            .login-wrapper {
                padding: 20px;
            }

            .login-container {
                max-width: 480px;

                display: block;

                border-radius: 26px;
            }

            .tools-panel {
                display: none;
            }

            .login-panel {
                min-height: auto;

                padding:
                    34px 26px 30px;
            }

            .mobile-brand {
                display: flex;

                align-items: center;

                justify-content: center;
            }

            .login-content {
                max-width: 100%;
            }

            .login-logo {
                width: 56px;
                height: 56px;

                border-radius: 16px;
            }

            .login-heading {
                text-align: center;
            }

            .login-heading h1 {
                font-size: 25px;
            }

            .login-heading p {
                font-size: 12px;
            }

            .login-form {
                margin-top: 25px;
            }

        }


        @media (max-width: 480px) {

            .login-wrapper {
                padding: 14px;
            }

            .login-container {
                border-radius: 23px;
            }

            .login-panel {
                padding:
                    30px 20px 25px;
            }

            .login-input {
                height: 50px;
            }

            .login-button {
                height: 50px;
            }

        }


        /* =====================================================
           REDUCE MOTION
        ====================================================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     BACKGROUND
========================================================= -->

<div class="login-background">

    <div class="login-grid"></div>

    <div class="orb orb-one"></div>

    <div class="orb orb-two"></div>

</div>


<!-- =========================================================
     LOGIN
========================================================= -->

<div class="login-wrapper">

    <div class="login-container">


        <!-- =================================================
             DESKTOP TOOLS
        ================================================== -->

        <section class="tools-panel">


            <div>

                <div class="tools-brand">

                    <div class="mini-logo">

                        <img
                            src="/assets/images/logo.png"
                            alt="RESTOCK"
                        >

                    </div>


                    <div class="tools-eyebrow">
                        RE-STOCK
                    </div>


                    <div class="tools-title">
                        Semua pencatatan toko,
                        satu tempat.
                    </div>


                    <p class="tools-description">
                        Kelola barang, belanja, penjualan,
                        titipan, pengeluaran, dan laporan
                        toko dengan lebih sederhana.
                    </p>

                </div>


                <!-- TOOLS -->

                <div class="tools-list">


                    <!-- BARANG -->

                    <div class="tool-item">

                        <div class="tool-icon">

                            <i
                                data-lucide="package"
                            ></i>

                        </div>

                        <div>

                            <div class="tool-name">
                                Barang
                            </div>

                            <div class="tool-caption">
                                Kelola stok
                            </div>

                        </div>

                    </div>


                    <!-- BELANJA -->

                    <div class="tool-item">

                        <div class="tool-icon">

                            <i
                                data-lucide="shopping-cart"
                            ></i>

                        </div>

                        <div>

                            <div class="tool-name">
                                Belanja
                            </div>

                            <div class="tool-caption">
                                Catat pembelian
                            </div>

                        </div>

                    </div>


                    <!-- PENJUALAN -->

                    <div class="tool-item">

                        <div class="tool-icon">

                            <i
                                data-lucide="receipt"
                            ></i>

                        </div>

                        <div>

                            <div class="tool-name">
                                Penjualan
                            </div>

                            <div class="tool-caption">
                                Transaksi toko
                            </div>

                        </div>

                    </div>


                    <!-- TITIPAN -->

                    <div class="tool-item">

                        <div class="tool-icon">

                            <i
                                data-lucide="handshake"
                            ></i>

                        </div>

                        <div>

                            <div class="tool-name">
                                Titipan
                            </div>

                            <div class="tool-caption">
                                Kelola penitip
                            </div>

                        </div>

                    </div>


                    <!-- PENGELUARAN -->

                    <div class="tool-item">

                        <div class="tool-icon">

                            <i
                                data-lucide="wallet"
                            ></i>

                        </div>

                        <div>

                            <div class="tool-name">
                                Pengeluaran
                            </div>

                            <div class="tool-caption">
                                Catat biaya
                            </div>

                        </div>

                    </div>


                    <!-- LAPORAN -->

                    <div class="tool-item">

                        <div class="tool-icon">

                            <i
                                data-lucide="chart-no-axes-combined"
                            ></i>

                        </div>

                        <div>

                            <div class="tool-name">
                                Laporan
                            </div>

                            <div class="tool-caption">
                                Pantau toko
                            </div>

                        </div>

                    </div>


                </div>

            </div>


            <div class="tools-footer">
                Sistem pencatatan toko sederhana
                untuk aktivitas sehari-hari.
            </div>

        </section>


        <!-- =================================================
             LOGIN PANEL
        ================================================== -->

        <section class="login-panel">

            <div class="login-content">


                <!-- MOBILE BRAND -->

                <div class="mobile-brand">

                    <div class="login-logo">

                        <img
                            src="/assets/images/logo.png"
                            alt="RESTOCK Logo"
                        >

                    </div>

                </div>


                <!-- HEADING -->

                <div class="login-heading">

                    <h1>
                        Selamat datang
                    </h1>

                    <p>
                        Masuk untuk melanjutkan ke RESTOCK.
                    </p>

                </div>


                <!-- ERROR -->

                <?php if ($error): ?>

                    <div class="login-error">

                        <i
                            data-lucide="circle-alert"
                        ></i>

                        <span>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form
                    method="POST"
                    class="login-form"
                    id="loginForm"
                >


                    <!-- USERNAME -->

                    <div class="field">

                        <label
                            for="username"
                            class="field-label"
                        >
                            Username
                        </label>


                        <div class="input-wrapper">

                            <div class="input-icon">

                                <i
                                    data-lucide="user-round"
                                ></i>

                            </div>


                            <input
                                type="text"
                                id="username"
                                name="username"
                                autocomplete="username"
                                required
                                autofocus
                                class="login-input"
                                placeholder="Masukkan username"
                                value="<?= htmlspecialchars(
                                    $_POST['username']
                                    ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="field">

                        <label
                            for="password"
                            class="field-label"
                        >
                            Password
                        </label>


                        <div class="input-wrapper">

                            <div class="input-icon">

                                <i
                                    data-lucide="lock-keyhole"
                                ></i>

                            </div>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                                class="login-input"
                                placeholder="Masukkan password"
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                id="passwordToggle"
                                aria-label="Tampilkan password"
                            >

                                <i
                                    data-lucide="eye"
                                    id="passwordIcon"
                                ></i>

                            </button>

                        </div>

                    </div>


                    <div class="flex items-center justify-between -mt-2 mb-1 gap-4">
                        <a href="/daftar-pilih.php" class="text-xs font-medium text-neutral-500 hover:text-neutral-900">Belum punya akun? Daftar</a>
                        <a href="/forgot-password.php" class="text-xs font-medium text-neutral-500 hover:text-neutral-900">Lupa password?</a>
                    </div>

                    <!-- BUTTON -->

                    <button
                        type="submit"
                        class="login-button"
                        id="loginButton"
                    >

                        <span id="loginButtonText">
                            Masuk
                        </span>

                        <i
                            data-lucide="arrow-right"
                            id="loginArrow"
                        ></i>

                    </button>


                </form>


                <!-- FOOTER -->

                <div class="login-footer">
                    RE-STOCK © <?= date('Y') ?>
                </div>

            </div>

        </section>


    </div>

</div>


<script src="https://unpkg.com/lucide@latest"></script>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | ICON
        |--------------------------------------------------------------------------
        */

        if (
            typeof lucide !== 'undefined'
        ) {

            lucide.createIcons();

        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD TOGGLE
        |--------------------------------------------------------------------------
        */

        const password =
            document.getElementById(
                'password'
            );

        const toggle =
            document.getElementById(
                'passwordToggle'
            );

        if (
            password &&
            toggle
        ) {

            toggle.addEventListener(
                'click',
                function () {

                    const isPassword =
                        password.type ===
                        'password';

                    password.type =
                        isPassword
                            ? 'text'
                            : 'password';

                    toggle.setAttribute(
                        'aria-label',
                        isPassword
                            ? 'Sembunyikan password'
                            : 'Tampilkan password'
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Ganti icon
                    |--------------------------------------------------------------------------
                    */

                    const icon =
                        document.getElementById(
                            'passwordIcon'
                        );

                    if (icon) {

                        icon.setAttribute(
                            'data-lucide',
                            isPassword
                                ? 'eye-off'
                                : 'eye'
                        );

                        if (
                            typeof lucide !==
                            'undefined'
                        ) {

                            lucide.createIcons();

                        }

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN LOADING
        |--------------------------------------------------------------------------
        */

        const form =
            document.getElementById(
                'loginForm'
            );

        const button =
            document.getElementById(
                'loginButton'
            );

        const buttonText =
            document.getElementById(
                'loginButtonText'
            );

        const arrow =
            document.getElementById(
                'loginArrow'
            );


        if (
            form &&
            button
        ) {

            form.addEventListener(
                'submit',
                function () {

                    button.classList.add(
                        'loading'
                    );

                    buttonText.textContent =
                        'Memeriksa...';


                    if (arrow) {

                        arrow.outerHTML =
                            '<span class="spinner"></span>';

                    }

                }
            );

        }

    }
);

</script>


</body>

</html>