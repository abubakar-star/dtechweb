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
$method = trim($_POST['payment_method'] ?? '');
$paymentDateInput = trim($_POST['payment_date'] ?? '');

$dateObject = DateTime::createFromFormat('!Y-m-d H:i:s', $paymentDateInput);
$dateErrors = DateTime::getLastErrors();
$validPaymentDate = $dateObject !== false
    && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
    && $dateObject->format('Y-m-d H:i:s') === $paymentDateInput;

if (
    $userId <= 0 ||
    empty($method) ||
    !$validPaymentDate
) {
    echo json_encode([
        "success" => false,
        "message" => "Missing or invalid required fields. Check the payment date and time."
    ]);
    exit;
}

// ================= GET USER PACKAGE =================

$stmt = $conn->prepare("
    SELECT u.package_id, p.price AS package_price
    FROM users u
    LEFT JOIN packages p ON p.id = u.package_id
    WHERE u.id = ?
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

if ($packageId === null || $user['package_price'] === null || (float)$user['package_price'] <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "This customer has no valid package price. Assign a package before recording payment."
    ]);
    exit;
}

// Always use the price configured for the customer's selected package.
// Do not trust a client-submitted amount.
$amount = (float)$user['package_price'];

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
    status,
    payment_date
)
VALUES
(
    ?, ?, ?, ?, ?, 'subscription', ?, ?, 'completed', ?
)
");

$stmt->bind_param(
    "iissdsss",
    $userId,
    $packageId,
    $reference,
    $invoiceNumber,
    $amount,
    $method,
    $transactionId,
    $paymentDateInput
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