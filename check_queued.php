<?php
include 'includes/logger.php';

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {

    error_log(
        'Database connection failed in check_queued.php'
    );

    die(json_encode([
        "latest_id" => 0
    ]));
}

$res = $conn->query("
    SELECT id, username 
    FROM users 
    WHERE status = 'queued'
    ORDER BY id DESC
    LIMIT 1
");

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();

    createLog(
    $conn,
    'queue',
    'queued_user_detected',
    'Queued user detected: ' . $row['username'],
    'info',
    $row['id']
);

    echo json_encode([
        "latest_id" => (int)$row['id'],
        "username"  => $row['username']
    ]);
} else {
    echo json_encode([
        "latest_id" => 0
    ]);
}
