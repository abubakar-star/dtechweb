<?php
session_start();

header('Content-Type: application/json');

require_once 'onasis_config.php';
require_once 'includes/logger.php';

/* =========================
   DATABASE CONNECTION
========================= */

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

$conn->set_charset("utf8mb4");

/* =========================
   SESSION CHECK
========================= */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);

    exit;
}

$user_id = (int) $_SESSION['user_id'];

$package_id = filter_input(
    INPUT_POST,
    'package_id',
    FILTER_VALIDATE_INT
);

$invoiceNumber = trim($_POST['invoice_number'] ?? '');

if (!$package_id || $package_id < 1) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid package selected"
    ]);

    exit;
}

/* =========================
   FETCH USER DETAILS
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        phone_number,
        username,
        account_number
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);

    exit;
}

$user = $result->fetch_assoc();

$phone = trim($user['phone_number']);

/* =========================
   FETCH SELECTED PACKAGE
========================= */

$pkg = $conn->prepare("
    SELECT
        p.id,
        p.package_name,
        p.price
    FROM packages p
    INNER JOIN users u
        ON u.connection_type = p.package_type
    WHERE p.id = ?
      AND u.id = ?
      AND p.status = 'active'
    LIMIT 1
");

$pkg->bind_param(
    "ii",
    $package_id,
    $user_id
);

$pkg->execute();

$pkgResult = $pkg->get_result();

if ($pkgResult->num_rows === 0) {

    echo json_encode([
        "success" => false,
        "message" => "Selected package is unavailable"
    ]);

    exit;
}

$package = $pkgResult->fetch_assoc();

$amount = (float) $package['price'];

/* =========================
   VALIDATE PAYMENT
========================= */

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

/* =========================
   CREATE UNIQUE REFERENCE
========================= */

$reference = "DLINK-" . $user_id . "-" . time();

/* =========================
   SAVE PENDING PAYMENT
========================= */

$insert = $conn->prepare("
    INSERT INTO payments
    (
        user_id,
        package_id,
        reference,
        amount,
        payment_type,
        invoice_number,
        status
    )
    VALUES
    (
        ?, ?, ?, ?, 'subscription', ?, 'pending'
    )
");

$insert->bind_param(
    "iisds",
    $user_id,
    $package_id,
    $reference,
    $amount,
    $invoiceNumber
);

if (!$insert->execute()) {

    createLog(
        $conn,
        'payment',
        'Payment record creation failed',
        $insert->error,
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "message" => "Could not create payment record"
    ]);

    exit;
}

/* =========================
   ZETUPAY REQUEST
========================= */

$data = [
    "phoneNumber" => $phone,
    "amount" => $amount,
    "reference" => $reference,
    "identifier" => (string) $user_id
];

$headers = [
    "Authorization: Bearer " . $ZETUPAY_SECRET_KEY,
    "Content-Type: application/json",
    "Accept: application/json",
    "Idempotency-Key: " . $reference
];

/* =========================
   SEND STK PUSH
========================= */

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

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

/* =========================
   CONNECTION ERROR
========================= */

if ($response === false) {

    $update = $conn->prepare("
        UPDATE payments
        SET status = 'failed',
            failure_reason = ?
        WHERE reference = ?
    ");

    $reason = "ZetuPay connection error: " . $curlError;

    $update->bind_param(
        "ss",
        $reason,
        $reference
    );

    $update->execute();

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

/* =========================
   DECODE RESPONSE
========================= */

$result = json_decode($response, true);

/* =========================
   SAVE RESPONSE LOG
========================= */

file_put_contents(
    "zetupay_package_log.txt",
    date('c') .
    " HTTP: " . $httpCode .
    " RESPONSE: " . $response . PHP_EOL,
    FILE_APPEND
);

/* =========================
   SUCCESSFUL STK REQUEST
========================= */

if (
    $httpCode == 202 &&
    isset($result['success']) &&
    $result['success'] === true &&
    isset($result['data'])
) {

    $paymentData = $result['data'];

    $transactionId =
        $paymentData['waveTransactionId'] ?? '';

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
        'ZetuPay package STK sent',
        'Reference: ' . $reference .
        ' | Package: ' . $package['package_name'] .
        ' | Amount: ' . $amount,
        'success',
        $user_id
    );

    echo json_encode([
        "success" => true,
        "http_code" => $httpCode,
        "transaction_id" => $transactionId,
        "payment_key" =>
            $paymentData['paymentKey'] ?? null,
        "reference" => $reference,
        "message" => "STK push sent. Check your phone."
    ]);

} else {

    $failureMessage =
        $result['message'] ??
        "ZetuPay payment initialization failed";

    $update = $conn->prepare("
        UPDATE payments
        SET
            status = 'failed',
            failure_reason = ?
        WHERE reference = ?
    ");

    $update->bind_param(
        "ss",
        $failureMessage,
        $reference
    );

    $update->execute();

    createLog(
        $conn,
        'payment',
        'ZetuPay initialization failed',
        json_encode($result),
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "http_code" => $httpCode,
        "message" => $failureMessage
    ]);
}

$conn->close();

exit;
?>
