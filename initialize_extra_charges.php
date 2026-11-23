<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/onasis_config_extra.php';
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
/*
========================================
ZETUPAY EXTRA CHARGE STATUS CHECKING
========================================
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['mode'] ?? '') === 'status') {

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "User not logged in"
        ]);

        exit;
    }

    $reference = trim((string)($_GET['reference'] ?? ''));
    $paymentKey = trim((string)($_GET['payment_key'] ?? ''));

    $userId = (int)$_SESSION['user_id'];

    if (
        $reference === '' ||
        !preg_match('/^[A-Za-z0-9_-]{8,150}$/', $paymentKey)
    ) {
        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid payment request"
        ]);

        exit;
    }

    // Verify the payment key belongs to this session and reference.
    $sessionPaymentKey =
        $_SESSION['extra_charge_payment_keys'][$reference] ?? '';

    if (
        !is_string($sessionPaymentKey) ||
        !hash_equals($sessionPaymentKey, $paymentKey)
    ) {
        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Payment verification failed"
        ]);

        exit;
    }

    // Verify payment ownership and payment type.
    $check = $conn->prepare("
        SELECT status, failure_reason
        FROM payments
        WHERE reference = ?
        AND user_id = ?
        AND payment_type = 'extra_charge'
        LIMIT 1
    ");

    $check->bind_param("si", $reference, $userId);
    $check->execute();

    $existing = $check->get_result()->fetch_assoc();

    if (!$existing) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Extra charge payment not found"
        ]);

        exit;
    }

    // Return already finalized payment status.
    if (in_array(
        $existing['status'],
        ['completed', 'failed', 'cancelled'],
        true
    )) {

        unset($_SESSION['extra_charge_payment_keys'][$reference]);

        echo json_encode([
            "success" => true,
            "status" => $existing['status'],
            "failure_reason" => $existing['failure_reason']
        ]);

        exit;
    }

    // Query ZetuPay directly.
    $statusUrl = rtrim($ZETUPAY_BASE_URL, '/') .
        '/payment/stk-push/' . rawurlencode($paymentKey);

    $ch = curl_init($statusUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $ZETUPAY_SECRET_KEY,
            "Accept: application/json"
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $gatewayResponse = curl_exec($ch);

    $curlError = curl_error($ch);

    $gatewayHttpCode = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($gatewayResponse === false) {

        createLog(
            $conn,
            'billing',
            'Extra charge status connection failed',
            $curlError,
            'warning',
            $userId
        );

        echo json_encode([
            "success" => false,
            "status" => "pending",
            "message" => "Unable to check payment status yet"
        ]);

        exit;
    }

    $decodedResponse = json_decode($gatewayResponse, true);

    if (
        !is_array($decodedResponse) ||
        $gatewayHttpCode < 200 ||
        $gatewayHttpCode >= 300
    ) {

        createLog(
            $conn,
            'billing',
            'Extra charge status check failed',
            substr((string)$gatewayResponse, 0, 2000),
            'warning',
            $userId
        );

        echo json_encode([
            "success" => false,
            "status" => "pending",
            "message" => "Payment status is not available yet"
        ]);

        exit;
    }

    $gatewayData = $decodedResponse['data'] ?? $decodedResponse;

    if (!is_array($gatewayData)) {
        $gatewayData = [];
    }

    $gatewayStatus = strtolower(trim(
        (string)($gatewayData['status'] ?? 'pending')
    ));

    $resultCode = (string)(
        $gatewayData['resultCode'] ?? ''
    );

    // Extract failure description.
    $failureReason = '';

    foreach ([
        'resultDesc',
        'resultDescription',
        'description',
        'message',
        'failureReason',
        'error'
    ] as $field) {

        if (
            isset($gatewayData[$field]) &&
            is_string($gatewayData[$field]) &&
            trim($gatewayData[$field]) !== ''
        ) {

            $failureReason = trim($gatewayData[$field]);
            break;
        }
    }

    // Process unsuccessful transactions.
    if (in_array($gatewayStatus, [
        'failed',
        'failure',
        'cancelled',
        'canceled',
        'expired'
    ], true)) {

        $finalStatus = (
            $resultCode === '1032' ||
            in_array($gatewayStatus, [
                'cancelled',
                'canceled'
            ], true)
        ) ? 'cancelled' : 'failed';

        if ($failureReason === '') {

            $failureReason = $resultCode !== ''
                ? "ZetuPay payment failed (result code {$resultCode})."
                : 'ZetuPay reported that the payment was not completed.';
        }

        $saveFailure = $conn->prepare("
            UPDATE payments
            SET status = ?, failure_reason = ?
            WHERE reference = ?
            AND user_id = ?
            AND payment_type = 'extra_charge'
            AND status = 'pending'
        ");

        $saveFailure->bind_param(
            "sssi",
            $finalStatus,
            $failureReason,
            $reference,
            $userId
        );

        $saveFailure->execute();

        createLog(
            $conn,
            'billing',
            'Extra charge payment unsuccessful',
            'Reference: ' . $reference .
            ' | Status: ' . $finalStatus .
            ' | Reason: ' . $failureReason,
            'warning',
            $userId
        );

        unset($_SESSION['extra_charge_payment_keys'][$reference]);

        echo json_encode([
            "success" => true,
            "status" => $finalStatus,
            "failure_reason" => $failureReason
        ]);

        exit;
    }

    // Successful payment must still be confirmed by webhook.
    $refresh = $conn->prepare("
        SELECT status, failure_reason
        FROM payments
        WHERE reference = ?
        AND user_id = ?
        AND payment_type = 'extra_charge'
        LIMIT 1
    ");

    $refresh->bind_param("si", $reference, $userId);
    $refresh->execute();

    $current = $refresh->get_result()->fetch_assoc();

    echo json_encode([
        "success" => true,
        "status" => $current['status'] ?? 'pending',
        "failure_reason" => $current['failure_reason'] ?? null,
        "gateway_status" => $gatewayStatus
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
   SEND ZETUPAY STK PUSH
========================= */
$data = [
    "phoneNumber" => $phone,
    "amount" => (int)$totalAmount,
    "reference" => $reference,
    "identifier" => (string)$user_id
];

$headers = [
    "Authorization: Bearer " . $ZETUPAY_SECRET_KEY,
    "Content-Type: application/json",
    "Accept: application/json",
    "Idempotency-Key: " . $reference
];

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

if ($response === false) {
    createLog(
        $conn,
        'billing',
        'Extra charge STK failed',
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

$result = json_decode($response, true);

file_put_contents(
    "zetupay_extra_charge_log.txt",
    date('c') . " HTTP: " . $httpCode . " RESPONSE: " . $response . PHP_EOL,
    FILE_APPEND
);

if (
    $httpCode == 202 &&
    isset($result['success']) &&
    $result['success'] === true &&
    isset($result['data']) &&
    !empty($result['data']['paymentKey'])
) {
    $paymentKey = (string)$result['data']['paymentKey'];

if (!isset($_SESSION['extra_charge_payment_keys'])) {
    $_SESSION['extra_charge_payment_keys'] = [];
}

$_SESSION['extra_charge_payment_keys'][$reference] = $paymentKey;
    $transactionId = $result['data']['waveTransactionId'] ?? '';

    if (!empty($transactionId)) {
        $update = $conn->prepare("UPDATE payments SET transaction_id = ? WHERE reference = ?");
        $update->bind_param("ss", $transactionId, $reference);
        $update->execute();
    }

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
    "http_code" => $httpCode,
    "transaction_id" => $transactionId,
    "payment_key" => $paymentKey,
    "reference" => $reference,
    "invoice_number" => $invoiceNumber,
    "amount" => $totalAmount,
    "message" => "STK push sent. Check your phone."
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
        "http_code" => $httpCode,
        "message" => $result['message'] ?? "Payment initialization failed"
    ]);
}

exit;
?>
