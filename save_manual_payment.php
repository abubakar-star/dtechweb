<?php

header('Content-Type: application/json');

// ================= DB CONNECTION =================

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

if ($conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

// ================= RECEIVE DATA =================

$userId = (int)($_POST['user_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$method = trim($_POST['payment_method'] ?? '');

if (
    $userId <= 0 ||
    $amount <= 0 ||
    empty($method)
) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields."
    ]);
    exit;
}

// ================= GET USER PACKAGE =================

$stmt = $conn->prepare("
    SELECT package_id
    FROM users
    WHERE id = ?
");

$stmt->bind_param("i", $userId);

$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

if (!$user) {

    echo json_encode([
        "success" => false,
        "message" => "Customer not found."
    ]);

    exit;
}

$packageId = $user['package_id'];

// ================= AUTO GENERATE IDS =================

$transactionId =
    'MAN' .
    date('YmdHis') .
    rand(1000,9999);

$reference =
    'REF' .
    date('YmdHis') .
    rand(1000,9999);

$invoiceNumber =
    'INV' .
    date('YmdHis') .
    rand(100,999);

// ================= INSERT PAYMENT =================

$stmt = $conn->prepare("
INSERT INTO payments
(
    user_id,
    package_id,
    reference,
    invoice_number,
    amount,
    payment_type,
    payment_method,
    transaction_id,
    status
)
VALUES
(
    ?, ?, ?, ?, ?, 'subscription', ?, ?, 'completed'
)
");

$stmt->bind_param(
    "iissdss",
    $userId,
    $packageId,
    $reference,
    $invoiceNumber,
    $amount,
    $method,
    $transactionId
);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Payment saved successfully."
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => $stmt->error
    ]);

}