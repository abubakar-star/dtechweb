<?php
// get_invoice_details.php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// DB connection

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname     = "dlink_network";

// DB connection
/*
$servername = "sql313.infinityfree.com";
$db_username = "if0_39741603";
$db_password = "mkala3771";
$dbname     = "if0_39741603_dlink_network";
*/

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$payment_date = $_GET['payment_date'] ?? null;

if (!$payment_date) {
    echo json_encode(["error" => "Payment date required"]);
    exit;
}

// Get package payment info
$stmt = $conn->prepare("SELECT amount FROM payments WHERE user_id = ? AND DATE(payment_date) = ? AND status='completed'");
$stmt->bind_param("is", $user_id, $payment_date);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc() ?? ["amount" => 0];
$stmt->close();

// Get installation fee if any paid on same date
$stmt2 = $conn->prepare("SELECT amount, title FROM installation_fees WHERE user_id = ? AND DATE(created_at) = ? AND is_active=1");
$stmt2->bind_param("is", $user_id, $payment_date);
$stmt2->execute();
$result2 = $stmt2->get_result();
$install_fee = $result2->fetch_assoc() ?? null;
$stmt2->close();

$conn->close();

echo json_encode([
    "payment" => $payment,
    "installation_fee" => $install_fee
]);
