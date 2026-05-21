<?php
session_start();
include 'includes/logger.php';

$user_id = $_SESSION['user_id'] ?? null;

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

// Clear all session variables
session_unset();
session_destroy();

// Delete cookies (both user and token)
if (isset($_COOKIE["remember_user"])) {
    include 'db.php';
    $username = $_COOKIE["remember_user"];
    $conn->query("UPDATE users SET remember_token=NULL WHERE username='$username'");
    $conn->close();
}
if (isset($_COOKIE["remember_token"])) {
    setcookie("remember_token", "", time() - 3600, "/");
}

// Redirect to login
header("Location: login.php");
exit();
?>
