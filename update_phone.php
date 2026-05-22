<?php

session_start();

require_once 'includes/logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);

    exit;
}

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

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

    createLog(
        $conn,
        'security',
        'Incorrect password during phone update',
        'User entered incorrect password while attempting to update phone number',
        'warning',
        $user_id
    );

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

$updated = $stmt->execute();

if (!$updated) {

    createLog(
        $conn,
        'account',
        'Phone update failed',
        'Database failed to update phone number: ' . $conn->error,
        'error',
        $user_id
    );

    echo json_encode([
        "status" => "error",
        "message" => "Failed to update phone number"
    ]);

    exit;
}

createLog(
    $conn,
    'account',
    'Phone number updated',
    'User successfully updated phone number',
    'info',
    $user_id
);

echo json_encode([
    "status" => "success"
]);
