<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed");
}

/* ===============================
   VALIDATE ID
================================ */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid template ID");
}

$id = (int) $_GET['id'];

/* ===============================
   DELETE TEMPLATE
================================ */
$stmt = $conn->prepare("
    DELETE FROM sms_templates
    WHERE id = ?
");

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    header("Location: sms_templates.php?deleted=1");
    exit();

} else {

    header("Location: sms_templates.php?deleted=0");
    exit();
}
?>