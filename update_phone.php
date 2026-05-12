<?php

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);

    exit;
}

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "dlink_network"
);

$user_id = $_SESSION['user_id'];

$password =
    trim($_POST['password'] ?? '');

$phone =
    trim($_POST['phone'] ?? '');

if (empty($password) || empty($phone)) {

    echo json_encode([
        "status" => "error",
        "message" => "All fields required"
    ]);

    exit;
}

/* Get current password */
$stmt = $conn->prepare(
    "SELECT password
     FROM users
     WHERE id=?"
);

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

if (!$user) {

    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);

    exit;
}

/* Verify password */
if ($password !== $user['password']) {

    echo json_encode([
        "status" => "error",
        "message" => "Incorrect password"
    ]);

    exit;
}

/* Update phone */
$stmt = $conn->prepare(
    "UPDATE users
     SET phone_number=?
     WHERE id=?"
);

$stmt->bind_param(
    "si",
    $phone,
    $user_id
);

$stmt->execute();

echo json_encode([
    "status" => "success"
]);