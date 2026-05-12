<?php
session_start();

// Clear all session variables
session_unset();
session_destroy();

// Delete cookies (both user and token)
if (isset($_COOKIE["remember_user"])) {
    $conn = new mysqli("localhost", "root", "", "dlink_network");
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
