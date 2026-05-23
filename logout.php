<?php
session_start();

include 'includes/logger.php';
include 'db.php';

$user_id = $_SESSION['user_id'] ?? null;

/*
=========================================
LOG USER LOGOUT
=========================================
*/

if ($user_id) {

    createLog(
        $conn,
        'authentication',
        'User Logout',
        'User logged out successfully',
        'info',
        $user_id
    );
}

/*
=========================================
CLEAR SESSION
=========================================
*/

session_unset();
session_destroy();

/*
=========================================
REMOVE REMEMBER TOKEN
=========================================
*/

if (isset($_COOKIE["remember_user"])) {

    $username = $_COOKIE["remember_user"];

    $stmt = $conn->prepare(
        "UPDATE users 
         SET remember_token = NULL 
         WHERE username = ?"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
}

/*
=========================================
DELETE COOKIES
=========================================
*/

setcookie("remember_user", "", time() - 3600, "/");
setcookie("remember_token", "", time() - 3600, "/");

/*
=========================================
CLOSE DATABASE
=========================================
*/

$conn->close();

/*
=========================================
REDIRECT
=========================================
*/

header("Location: login.php");
exit();
?>
