<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Not logged in"
    ]);
    exit;
}

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli(
    $host,
    $username,
    $password,
    $dbname,
    $port
);

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total
    FROM extra_charges
    WHERE user_id = ?
    AND status = 'pending'
");

$stmt->bind_param(
    "i",
    $_SESSION['user_id']
);

$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode([
    "success" => true,
    "total_extra_charges" => (float)$row['total']
]);