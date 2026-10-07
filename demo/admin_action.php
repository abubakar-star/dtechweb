<?php

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("DB error");
}

$binding_id = (int)$_POST['binding_id'];

if (isset($_POST['approve'])) {
    $stmt = $conn->prepare("UPDATE bindings SET status='approved' WHERE id=?");
    $stmt->bind_param("i", $binding_id);
    $stmt->execute();
}

if (isset($_POST['reject'])) {
    $stmt = $conn->prepare("UPDATE bindings SET status='rejected' WHERE id=?");
    $stmt->bind_param("i", $binding_id);
    $stmt->execute();
}

if (isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM bindings WHERE id=?");
    $stmt->bind_param("i", $binding_id);
    $stmt->execute();
}

header("Location: admin_bindings.php");
exit();
