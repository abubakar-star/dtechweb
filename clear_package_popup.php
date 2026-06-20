<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$stmt = $conn->prepare("
    UPDATE users
    SET package_updated_popup = 0
    WHERE id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->close();

echo "done";