<?php

date_default_timezone_set('Africa/Nairobi');

/*
|--------------------------------------------------------------------------
| SESSION SECURITY
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/*
|--------------------------------------------------------------------------
| ALREADY AUTHENTICATED
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['dlink_admin_authenticated']) &&
    $_SESSION['dlink_admin_authenticated'] === true
) {
    header('Location: tosha.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| ADMINISTRATOR SECRET FROM RENDER
|--------------------------------------------------------------------------
*/

$adminKey = getenv('DTECH_ADMIN_KEY');

if (!$adminKey && isset($_ENV['DTECH_ADMIN_KEY'])) {
    $adminKey = $_ENV['DTECH_ADMIN_KEY'];
}

$error = '';

/*
|--------------------------------------------------------------------------
| LOGIN RATE LIMIT
|--------------------------------------------------------------------------
*/

$attempts = $_SESSION['admin_login_attempts'] ?? 0;

$lockUntil = $_SESSION['admin_lock_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (time() < $lockUntil) {

        $error = 'Too many attempts. Please wait before trying again.';

    } elseif (!isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['admin_csrf'] ?? '',
            $_POST['csrf_token']
        )
    ) {

        $error = 'Invalid request. Refresh the page and try again.';

    } elseif (!$adminKey) {

        $error = 'Administrator security key is not configured. Contact the website administrator.';

        error_log('D-LINK: DTECH_ADMIN_KEY is missing.');

    } else {

        $submittedKey = $_POST['admin_key'] ?? '';

        if (is_string($submittedKey) &&
            hash_equals($adminKey, $submittedKey)
        ) {

            session_regenerate_id(true);

            $_SESSION['dlink_admin_authenticated'] = true;

            $_SESSION['dlink_admin_login_time'] = time();

            unset(
                $_SESSION['admin_login_attempts'],
                $_SESSION['admin_lock_until']
            );

            header('Location: tosha.php');
            exit();

        } else {

            $attempts++;

            $_SESSION['admin_login_attempts'] = $attempts;

            if ($attempts >= 5) {

                $_SESSION['admin_lock_until'] = time() + 60;

                $_SESSION['admin_login_attempts'] = 0;

                $error = 'Too many incorrect attempts. Try again after 60 seconds.';

            } else {

                $error = 'Incorrect administrator password.';

            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_csrf'])) {

    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Administrator Login | D-LINK Network</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center p-5 text-white">

<div class="w-full max-w-md bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl p-8">

    <div class="text-center mb-8">

        <div class="text-5xl mb-4">🔐</div>

        <h1 class="text-2xl font-bold">
            D-LINK Administrator
        </h1>

        <p class="text-slate-400 text-sm mt-2">
            Secure administrator authentication
        </p>

    </div>

    <?php if ($error): ?>

        <div class="bg-red-500/10 border border-red-500/30 text-red-300 p-3 rounded-lg text-sm mb-5">

            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

        </div>

    <?php endif; ?>

    <form method="POST" action="admin_login.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <label class="block text-sm text-slate-300 mb-2">

            Administrator Password

        </label>

        <input
            type="password"
            name="admin_key"
            required
            maxlength="255"
            autocomplete="current-password"
            placeholder="Enter administrator password"
            class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-3 outline-none focus:border-blue-500 mb-5"
        >

        <button
            type="submit"
            class="w-full bg-blue-600 hover:bg-blue-700 transition rounded-lg py-3 font-bold"
        >

            Login to Admin Panel

        </button>

    </form>

    <p class="text-center text-xs text-slate-500 mt-7">

        D-LINK Network Administration System

    </p>

</div>

</body>
</html>