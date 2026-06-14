<?php

header('Content-Type: application/json');

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

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$ids = $data['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode([
        'success' => false,
        'message' => 'No IDs provided'
    ]);
    exit;
}

$ids = array_unique(array_map('intval', $ids));

$idList = implode(',', $ids);

$sql = "
    UPDATE payments
    SET viewed = 1
    WHERE viewed = 0
    AND id IN ($idList)
";

if ($conn->query($sql)) {
    echo json_encode([
        'success' => true,
        'updated' => $conn->affected_rows
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $conn->error
    ]);
}