<?php
// db.php
// ===============================
// Database Connection
// ===============================

date_default_timezone_set("Africa/Nairobi"); // Kenya time zone

// Database credentials
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

// Create connection
$conn = new mysqli($host, $username, $password, $dbname, $port);

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
