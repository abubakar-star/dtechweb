<?php
session_start();

header('Content-Type: application/json');

include 'onasis_config_extra.php';
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
        'Unauthorized extra charge payment',
        'Attempt to initialize payment without login',
        'warning'
    );

    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

/* =========================
   GET USER DETAILS
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

$phone = $user['phone_number'];
$accountNumber = $user['account_number'];

/* =========================
   GET TOTAL PENDING CHARGES
========================= */
$chargeStmt = $conn->prepare("
    SELECT
        SUM(amount) AS total_amount,
        COUNT(*) AS total_charges
    FROM extra_charges
    WHERE user_id = ?
    AND status = 'pending'
");

$chargeStmt->bind_param("i", $user_id);
$chargeStmt->execute();

$chargeResult = $chargeStmt->get_result();
$chargeData = $chargeResult->fetch_assoc();

$totalAmount = (float)($chargeData['total_amount'] ?? 0);
$totalCharges = (int)($chargeData['total_charges'] ?? 0);

if ($totalAmount <= 0 || $totalCharges <= 0) {

    echo json_encode([
        "success" => false,
        "message" => "No pending extra charges found"
    ]);
    exit;
}

/* =========================
   GENERATE INVOICE NUMBER
========================= */
$invoiceNumber =
    "INV-EXTRA-" .
    date('YmdHis') .
    "-" .
    $user_id;

/* =========================
   GENERATE REFERENCE
========================= */
$reference =
    "DLINK-EXTRA-" .
    $user_id .
    "-" .
    time();

/* =========================
   CREATE PAYMENT RECORD
========================= */
$insert = $conn->prepare("
    INSERT INTO payments
    (
        user_id,
        reference,
        invoice_number,
        amount,
        payment_type,
        status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        'extra_charge',
        'pending'
    )
");

$insert->bind_param(
    "issd",
    $user_id,
    $reference,
    $invoiceNumber,
    $totalAmount
);

$insert->execute();

createLog(
    $conn,
    'billing',
    'Extra charge payment created',
    'Reference: ' . $reference .
    ' | Amount: ' . $totalAmount,
    'info',
    $user_id
);

/* =========================
   SEND STK PUSH
========================= */
$data = [
    "phone" => $phone,
    "amount" => $totalAmount,
    "reference" => $reference,
    "account_ref" => $accountNumber,
    "callback_url" => "https://dtechweb.onrender.com/onasis_sub_callback.php"
];

$headers = [
    "x-api-key: " . $ONASIS_SECRET_KEY,
    "Content-Type: application/json",
    "Accept: application/json"
];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $ONASIS_BASE_URL . "/api/stk");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

if ($response === false) {

    createLog(
        $conn,
        'billing',
        'Extra charge STK failed',
        curl_error($ch),
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "message" => curl_error($ch)
    ]);

    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

file_put_contents(
    "extra_charge_stk_log.txt",
    date('c') . " " . $response . PHP_EOL,
    FILE_APPEND
);

/* =========================
   RESPONSE
========================= */
if ($httpCode == 200 && isset($result['status'])) {

    createLog(
        $conn,
        'billing',
        'Extra charge STK sent',
        'Reference: ' . $reference,
        'success',
        $user_id
    );

    echo json_encode([
        "success" => true,
        "reference" => $reference,
        "invoice_number" => $invoiceNumber,
        "amount" => $totalAmount,
        "message" => "STK push sent successfully"
    ]);

} else {

    createLog(
        $conn,
        'billing',
        'Extra charge STK failed',
        json_encode($result),
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "message" => "Payment Server Unreachable",
        "response" => $result
    ]);
}

exit;
?>
