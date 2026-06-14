<?php

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

$data = json_decode(
    file_get_contents('php://input'),
    true
);

$ids = $data['ids'] ?? [];

if(empty($ids)){
    exit;
}

$ids = array_map('intval', $ids);

$conn->query("
    UPDATE payments
    SET viewed = 1
    WHERE id IN (" .
    implode(',', $ids) .
    ")
");