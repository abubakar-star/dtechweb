<?php
session_start();

header('Content-Type: application/json');

include 'onasis_config_change.php';
include 'includes/logger.php';

/* =========================
   DB CONNECTION
========================= */
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

/* =========================
   SESSION CHECK
========================= */
if (!isset($_SESSION['user_id'])) {

createLog(
    $conn,
    'security',
    'Unauthorized payment initialization',
    'Attempt to initialize STK without login session',
    'warning'
);

    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

$package_id = $_POST['package_id'] ?? '';

if (empty($package_id)) {

    echo json_encode([
        "success" => false,
        "message" => "Package not selected"
    ]);
    exit;
}

/* =========================
   GET USER DETAILS
========================= */

$sql = "
SELECT 
    id,
    phone_number,
    username,
    account_number
FROM users
WHERE id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    createLog(
        $conn,
        'payment',
        'User lookup failed',
        'No user found during STK initialization',
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);

    exit;
}

$user = $result->fetch_assoc();

/* =========================
   GET SELECTED PACKAGE
========================= */

$pkg = $conn->prepare("
SELECT id, package_name, price
FROM packages
WHERE id = ?
LIMIT 1
");

$pkg->bind_param("i", $package_id);
$pkg->execute();

$pkgResult = $pkg->get_result();

if ($pkgResult->num_rows == 0) {

    echo json_encode([
        "success" => false,
        "message" => "Selected package not found"
    ]);

    exit;
}

$package = $pkgResult->fetch_assoc();

/* =========================
   ASSIGN VARIABLES
========================= */

$phone = $user['phone_number'];
$amount = $package['price'];
$accountNumber = $user['account_number'];

$invDteInv = $_POST['invoice_number'] ?? '';

/* =========================
   GET USER DETAILS
========================= */


$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

createLog(
    $conn,
    'payment',
    'User lookup failed',
    'No user found during STK initialization',
    'error',
    $user_id
);

    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

$user = $result->fetch_assoc();

$phone = $user['phone_number'];
$accountNumber = $user['account_number'];

/* =========================
   CREATE REFERENCE
========================= */
$reference = "DLINK-" . $user['id'] . "-" . time();

/* =========================
   SAVE PAYMENT (PENDING)
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
    $invDteInv
);

$insert->execute();

createLog(
    $conn,
    'payment',
    'Pending payment created',
    'Reference: ' . $reference .
    ' | Amount: ' . $amount,
    'info',
    $user_id
);

/* =========================
   PREPARE ZETUPAY REQUEST
========================= */

$data = [
    "phoneNumber" => $phone,
    "amount" => (int)$amount,
    "reference" => $reference,
    "identifier" => (string)$user_id
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

/* =========================
   CURL ERROR CHECK
========================= */

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

/* =========================
   DECODE RESPONSE
========================= */

$result = json_decode($response, true);

/* =========================
   LOG RESPONSE
========================= */

file_put_contents(
    "zetupay_sub_log.txt",
    date('c') . " HTTP: " . $httpCode .
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
        'ZetuPay subscription STK push sent',
        'Reference: ' . $reference,
        'success',
        $user_id
    );

    echo json_encode([
        "success" => true,
        "http_code" => $httpCode,
        "transaction_id" => $transactionId,
        "reference" => $reference,
        "message" => "STK push sent. Check your phone."
    ]);

} else {

    createLog(
        $conn,
        'payment',
        'ZetuPay subscription initialization failed',
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
