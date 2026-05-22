<?php
// db.php
// ===============================
// Database Connection
// ===============================

date_default_timezone_set("Africa/Nairobi"); // Kenya time zone
require_once 'includes/logger.php';

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
$query = "
    UPDATE users
    SET status = 'inactive'
    WHERE status = 'active'
      AND DATE_ADD(created_at, INTERVAL 30 DAY) < CURDATE()
";

$result = $conn->query($query);

if ($result) {

    $affected = $conn->affected_rows;

    createLog(
        $conn,
        'system',
        'Expiry update completed',
        'Automatic expiry checker deactivated ' . $affected . ' user(s)',
        'info',
        null
    );

} else {

    createLog(
        $conn,
        'system',
        'Expiry update failed',
        'Failed to update expired users: ' . $conn->error,
        'error',
        null
    );

}
?>
