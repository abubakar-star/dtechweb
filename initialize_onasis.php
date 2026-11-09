<?php
session_start();

header('Content-Type: application/json');

include 'onasis_config.php';
include 'includes/logger.php';

/* DATABASE CONNECTION */
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

/* SESSION CHECK */
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$invoiceNumber = $_POST['invoice_number'] ?? '';

/* GET USER DETAILS */
$sql = "SELECT
            users.id,
            users.phone_number,
            users.username,
            users.account_number,
            packages.price
        FROM users
        LEFT JOIN packages
        ON users.package_id = packages.id
        WHERE users.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

$user = $result->fetch_assoc();

$phone = trim($user['phone_number']);
$amount = (int)$user['price'];
$accountNumber = $user['account_number'];

if ($amount < 1 || $amount > 250000) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid payment amount"
    ]);
    exit;
}

if (empty($phone)) {
    echo json_encode([
        "success" => false,
        "message" => "Customer phone number missing"
    ]);
    exit;
}

/* CREATE UNIQUE REFERENCE */
$reference = "DLINK-" . $user['id'] . "-" . time();

/* SAVE PENDING PAYMENT */
$insert = $conn->prepare("
    INSERT INTO payments
    (user_id, reference, amount, invoice_number, status)
    VALUES (?, ?, ?, ?, 'pending')
");

$insert->bind_param(
    "isds",
    $user_id,
    $reference,
    $amount,
    $invoiceNumber
);

if (!$insert->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Could not create payment record"
    ]);
    exit;
}

/* ZETUPAY REQUEST */
$data = [
    "phoneNumber" => $phone,
    "amount" => $amount,
    "reference" => $reference,
    "identifier" => (string)$user_id
];

$headers = [
    "Authorization: Bearer " . $ZETUPAY_SECRET_KEY,
    "Content-Type: application/json",
    "Accept: application/json",
    "Idempotency-Key: " . $reference
];

/* SEND STK PUSH */
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $ZETUPAY_BASE_URL . "/payment/stk-push",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);

$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

/* CURL ERROR */
if ($response === false) {

    createLog(
        $conn,
        'payment',
        'ZetuPay connection failed',
        $curlError,
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "message" => "Payment gateway connection failed"
    ]);

    exit;
}

/* DECODE RESPONSE */
$result = json_decode($response, true);

/* SAVE API RESPONSE LOG */
file_put_contents(
    "zetupay_log.txt",
    date('c') . " HTTP: " . $httpCode .
    " RESPONSE: " . $response . PHP_EOL,
    FILE_APPEND
);

/* SUCCESSFUL STK REQUEST */
if (
    $httpCode == 202 &&
    isset($result['success']) &&
    $result['success'] === true &&
    isset($result['data'])
) {

    $paymentData = $result['data'];

    $transactionId = $paymentData['waveTransactionId'] ?? '';

    if (!empty($transactionId)) {

        $update = $conn->prepare("
            UPDATE payments
            SET transaction_id = ?
            WHERE reference = ?
        ");

        $update->bind_param(
            "ss",
            $transactionId,
            $reference
        );

        $update->execute();
    }

    createLog(
        $conn,
        'payment',
        'ZetuPay STK push sent',
        'Reference: ' . $reference,
        'success',
        $user_id
    );

    echo json_encode([
        "success" => true,
        "http_code" => $httpCode,
        "transaction_id" => $transactionId,
        "payment_key" => $paymentData['paymentKey'] ?? null,
        "reference" => $reference,
        "message" => "STK push sent. Check your phone."
    ]);

} else {

    createLog(
        $conn,
        'payment',
        'ZetuPay payment initialization failed',
        json_encode($result),
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "http_code" => $httpCode,
        "message" => $result['message'] ?? "Payment initialization failed"
    ]);
}

exit;
?>
