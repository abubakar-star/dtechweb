<?php
// get_invoice_details.php
session_start();

include 'includes/logger.php';

if (!isset($_SESSION['user_id'])) {

    createLog(
        null,
        null,
        null,
        'security',
        'unauthorized_invoice_access',
        'Unauthorized access attempt to get_invoice_details.php',
        'warning'
    );

    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}
   
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {

    error_log(
        "Database connection failed in get_invoice_details.php"
    );

    die("Database connection failed");
}

$user_id = $_SESSION['user_id'];
$payment_date = $_GET['payment_date'] ?? null;

if (!$payment_date) {

    createLog(
        $conn,
        $_SESSION['user_id'],
        null,
        'invoice',
        'missing_payment_date',
        'Invoice request attempted without payment date',
        'warning'
    );

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
