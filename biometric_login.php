<?php
session_start();

require 'db.php';

header('Content-Type: application/json');

$userId = $_POST['user_id'] ?? '';

if (empty($userId)) {

    echo json_encode([
        'success' => false,
        'message' => 'Missing user ID'
    ]);

    exit;
}

$stmt = $conn->prepare("
    SELECT id, username
    FROM users
    WHERE id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

     echo json_encode([
        'success' => true,
        'username' => $user['username']
    ]);

} else {

    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
}
