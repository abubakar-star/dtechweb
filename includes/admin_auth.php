<?php

/**
 * D-LINK ADMINISTRATOR AUTHENTICATION
 *
 * Protects administrator pages.
 * Requires successful authentication through admin_login.php.
 */

date_default_timezone_set('Africa/Nairobi');

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| CHECK ADMINISTRATOR AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['dlink_admin_authenticated']) ||
    $_SESSION['dlink_admin_authenticated'] !== true
) {

    header('Location: /admin_login.php');
    exit();

}

/*
|--------------------------------------------------------------------------
| AUTOMATIC SESSION TIMEOUT
|--------------------------------------------------------------------------
|
| Administrator is logged out after 30 minutes of inactivity.
|
*/

$timeout = 30 * 60;

$lastActivity = $_SESSION['dlink_admin_last_activity'] ?? time();

if ((time() - $lastActivity) > $timeout) {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );

    }

    session_destroy();

    header('Location: /admin_login.php?expired=1');
    exit();

}

/*
|--------------------------------------------------------------------------
| UPDATE LAST ACTIVITY
|--------------------------------------------------------------------------
*/

$_SESSION['dlink_admin_last_activity'] = time();

/*
|--------------------------------------------------------------------------
| CONFIRM ADMINISTRATOR ACCESS
|--------------------------------------------------------------------------
*/

$_SESSION['dlink_admin_authenticated'] = true;

?>