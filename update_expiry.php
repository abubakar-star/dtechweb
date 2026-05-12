<?php
// db.php
// ===============================
// Database Connection
// ===============================

date_default_timezone_set("Africa/Nairobi"); // Kenya time zone

// Database credentials
$servername = "sql313.infinityfree.com";
$username   = "if0_39741603";
$password   = "mkala3771";
$dbname     = "if0_39741603_dlink_network";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ===============================
// Update inactive users based on created_at
// ===============================

// Assuming each subscription is 30 days
$conn->query("
    UPDATE users
    SET status = 'inactive'
    WHERE status = 'active'
      AND DATE_ADD(created_at, INTERVAL 30 DAY) < CURDATE()
");
?>
