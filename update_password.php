<?php
// update_password.php
session_start();
header('Content-Type: application/json');

// Ensure user is logged in via session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please log in.']);
    exit();
}

// DB connection — update credentials if needed
$host   = "sql313.infinityfree.com";
$dbuser = "if0_39741603";
$dbpass = "mkala3771";
$dbname = "if0_39741603_dlink_network";

$conn = new mysqli($host, $dbuser, $dbpass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Basic validation of POST data
$currentPassword = isset($_POST['currentPassword']) ? trim($_POST['currentPassword']) : '';
$newPassword     = isset($_POST['newPassword']) ? trim($_POST['newPassword']) : '';

if ($currentPassword === '' || $newPassword === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please provide current and new password.']);
    exit();
}

// fetch current password from DB (plaintext comparison)
$sql = "SELECT password FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($dbPassword);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit();
}
$stmt->close();

// Compare plaintext passwords
if ($currentPassword !== $dbPassword) {
    echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
    exit();
}

// (Optional) basic new password rules — minimal check
if (strlen($newPassword) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'New password must be at least 6 characters.']);
    exit();
}

// Update the users table with the new plaintext password
$updateSql = "UPDATE users SET password = ? WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
if (!$updateStmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}
$updateStmt->bind_param("si", $newPassword, $user_id);
$ok = $updateStmt->execute();
$updateStmt->close();

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update password.']);
    exit();
}

// Insert into history table if exists (optional)
$insertSql = "INSERT INTO user_passwords (user_id, password) VALUES (?, ?)";
$insertStmt = $conn->prepare($insertSql);
if ($insertStmt) {
    $insertStmt->bind_param("is", $user_id, $newPassword);
    $insertStmt->execute();
    $insertStmt->close();
}
// else: it's okay if user_passwords table doesn't exist; we silently ignore

$conn->close();

echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
exit();
?>
