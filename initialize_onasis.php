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

/*
 * SERVER-SIDE ZETUPAY STATUS CHECK
 * The browser never receives the ZetuPay secret key. It supplies the
 * paymentKey returned by the STK initialization plus its D-LINK reference.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['mode'] ?? '') === 'status') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "User not logged in"]);
        exit;
    }

    $reference = trim((string)($_GET['reference'] ?? ''));
    $paymentKey = trim((string)($_GET['payment_key'] ?? ''));

    if ($reference === '' || !preg_match('/^[A-Za-z0-9_-]{8,150}$/', $paymentKey)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid payment status request"]);
        exit;
    }

    $check = $conn->prepare("SELECT status, failure_reason FROM payments WHERE reference = ? AND user_id = ? LIMIT 1");
    $check->bind_param("si", $reference, $_SESSION['user_id']);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Payment not found"]);
        exit;
    }

    if (in_array($existing['status'], ['completed', 'failed', 'cancelled'], true)) {
        echo json_encode([
            "success" => true,
            "status" => $existing['status'],
            "failure_reason" => $existing['failure_reason']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $statusUrl = rtrim($ZETUPAY_BASE_URL, '/') . '/payment/stk-push/' . rawurlencode($paymentKey);
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
    $gatewayHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($gatewayResponse === false) {
        createLog($conn, 'payment', 'ZetuPay status check connection failed', $curlError, 'warning', (int)$_SESSION['user_id']);
        echo json_encode(["success" => false, "status" => "pending", "message" => "Unable to check payment status yet"]);
        exit;
    }

    $gatewayData = json_decode($gatewayResponse, true);

    if (!is_array($gatewayData) || $gatewayHttpCode < 200 || $gatewayHttpCode >= 300) {
        createLog($conn, 'payment', 'ZetuPay status check failed', substr((string)$gatewayResponse, 0, 2000), 'warning', (int)$_SESSION['user_id']);
        echo json_encode(["success" => false, "status" => "pending", "message" => "Payment status is not available yet"]);
        exit;
    }

    $gatewayStatus = strtolower(trim((string)($gatewayData['status'] ?? 'pending')));
    $resultCode = (string)($gatewayData['resultCode'] ?? '');
    $failureReason = '';

    foreach (['resultDesc', 'resultDescription', 'description', 'message', 'failureReason', 'error'] as $field) {
        if (isset($gatewayData[$field]) && is_string($gatewayData[$field]) && trim($gatewayData[$field]) !== '') {
            $failureReason = trim($gatewayData[$field]);
            break;
        }
    }

    // ZetuPay reports cancelled/ignored STK prompts as failed; resultCode 1032 identifies cancellation.
    if (in_array($gatewayStatus, ['failed', 'failure', 'cancelled', 'canceled'], true)) {
        $finalStatus = ($resultCode === '1032' || in_array($gatewayStatus, ['cancelled', 'canceled'], true))
            ? 'cancelled'
            : 'failed';

        if ($failureReason === '') {
            $failureReason = $resultCode !== ''
                ? "ZetuPay payment failed (result code {$resultCode})."
                : 'ZetuPay reported that the payment was not completed.';
        }

        $saveFailure = $conn->prepare("
            UPDATE payments
            SET status = ?, failure_reason = ?
            WHERE reference = ? AND user_id = ? AND status = 'pending'
        ");
        $saveFailure->bind_param("sssi", $finalStatus, $failureReason, $reference, $_SESSION['user_id']);
        $saveFailure->execute();

        createLog(
            $conn,
            'payment',
            'ZetuPay payment failed',
            'Reference: ' . $reference . ' | Status: ' . $finalStatus . ' | Reason: ' . $failureReason,
            'warning',
            (int)$_SESSION['user_id']
        );

        echo json_encode([
            "success" => true,
            "status" => $finalStatus,
            "failure_reason" => $failureReason
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Do not mark a payment completed from a browser poll. The signed webhook remains authoritative.
    $refresh = $conn->prepare("SELECT status, failure_reason FROM payments WHERE reference = ? AND user_id = ? LIMIT 1");
    $refresh->bind_param("si", $reference, $_SESSION['user_id']);
    $refresh->execute();
    $current = $refresh->get_result()->fetch_assoc();

    echo json_encode([
        "success" => true,
        "status" => $current['status'] ?? 'pending',
        "failure_reason" => $current['failure_reason'] ?? null,
        "gateway_status" => $gatewayStatus
    ], JSON_UNESCAPED_UNICODE);
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
$accountNumber = trim((string)($user['account_number'] ?? ''));

if ($amount < 1 || $amount > 250000) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid payment amount"
    ]);
    exit;
}

if ($accountNumber === '' || strlen($accountNumber) > 100 || !ctype_alnum($accountNumber)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid Centipede account number. Use the exact account number with letters and numbers only."
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
    // ZetuPay forwards "reference" as the dynamic Paybill account reference.
    "reference" => $accountNumber,
    // Keep the unique D-LINK payment reference for webhook matching.
    "identifier" => $reference
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
