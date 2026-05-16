<?php

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

$res = $conn->query("
    SELECT id, username 
    FROM users 
    WHERE status = 'queued'
    ORDER BY id DESC
    LIMIT 1
");

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode([
        "latest_id" => (int)$row['id'],
        "username"  => $row['username']
    ]);
} else {
    echo json_encode([
        "latest_id" => 0
    ]);
}
