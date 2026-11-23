<?php
session_start();

header('Content-Type: application/json');

include 'onasis_config_change.php';
include 'includes/logger.php';

/* DATABASE CONNECTION */

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

/*
=========================================
SERVER-SIDE ZETUPAY PAYMENT STATUS CHECK
PACKAGE CHANGE
=========================================
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['mode'] ?? '') === 'status') {

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

    if (
        $reference === '' ||
        !preg_match('/^[A-Za-z0-9_-]{8,150}$/', $paymentKey)
    ) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid payment status request"
        ]);
        exit;
    }

    // Verify payment belongs to logged-in customer
    $check = $conn->prepare("
        SELECT status, failure_reason
        FROM payments
        WHERE reference = ?
        AND user_id = ?
        LIMIT 1
    ");

    $check->bind_param("si", $reference, $_SESSION['user_id']);
    $check->execute();

    $existing = $check->get_result()->fetch_assoc();

    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Payment not found"
        ]);
        exit;
    }

    // Do not check transactions already finalized
    if (in_array($existing['status'], ['completed', 'failed', 'cancelled'], true)) {

        echo json_encode([
            "success" => true,
            "status" => $existing['status'],
            "failure_reason" => $existing['failure_reason']
        ]);

        exit;
    }

    // Query ZetuPay directly
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
    $gatewayHttpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($gatewayResponse === false) {

        createLog(
            $conn,
            'payment',
            'Package change ZetuPay status connection failed',
            $curlError,
            'warning',
            (int)$_SESSION['user_id']
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
            'payment',
            'Package change ZetuPay status check failed',
            substr((string)$gatewayResponse, 0, 2000),
            'warning',
            (int)$_SESSION['user_id']
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

    $resultCode = (string)($gatewayData['resultCode'] ?? '');

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

    // Handle unsuccessful transactions
    if (in_array($gatewayStatus, [
        'failed',
        'failure',
        'cancelled',
        'canceled',
        'expired'
    ], true)) {

        $finalStatus = (
            $resultCode === '1032' ||
            in_array($gatewayStatus, ['cancelled', 'canceled'], true)
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
            AND status = 'pending'
        ");

        $saveFailure->bind_param(
            "sssi",
            $finalStatus,
            $failureReason,
            $reference,
            $_SESSION['user_id']
        );

        $saveFailure->execute();

        createLog(
            $conn,
            'payment',
            'ZetuPay package change payment failed',
            'Reference: ' . $reference .
            ' | Status: ' . $finalStatus .
            ' | Reason: ' . $failureReason,
            'warning',
            (int)$_SESSION['user_id']
        );

        echo json_encode([
            "success" => true,
            "status" => $finalStatus,
            "failure_reason" => $failureReason
        ]);

        exit;
    }

    // Successful payments must still be confirmed by the webhook.
    $refresh = $conn->prepare("
        SELECT status, failure_reason
        FROM payments
        WHERE reference = ?
        AND user_id = ?
        LIMIT 1
    ");

    $refresh->bind_param("si", $reference, $_SESSION['user_id']);
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

/* SESSION CHECK */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);

    exit;
}

$user_id = (int)$_SESSION['user_id'];

$package_id = (int)($_POST['package_id'] ?? 0);

$invoiceNumber = $_POST['invoice_number'] ?? '';

if ($package_id < 1) {

    echo json_encode([
        "success" => false,
        "message" => "Package not selected"
    ]);

    exit;
}

/* GET USER DETAILS */

$stmt = $conn->prepare("
    SELECT
        id,
        phone_number,
        username,
        account_number
    FROM users
    WHERE id = ?
");

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

$accountNumber = trim((string)($user['account_number'] ?? ''));

/* GET SELECTED PACKAGE */

$pkg = $conn->prepare("
    SELECT
        id,
        package_name,
        price
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

/* ASSIGN PACKAGE PRICE */

$amount = (float)$package['price'];

if ($amount < 1 || $amount > 250000) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid payment amount"
    ]);

    exit;
}

if (
    $accountNumber === '' ||
    strlen($accountNumber) > 100 ||
    !ctype_alnum($accountNumber)
) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid Centipede account number. Check your account number."
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

$reference = "DLINK-" . $user_id . "-" . time();

/* SAVE PENDING PAYMENT */

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
    (?, ?, ?, ?, 'subscription', ?, 'pending')
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
        'Pending payment creation failed',
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

createLog(
    $conn,
    'payment',
    'Pending subscription payment created',
    'Reference: ' . $reference .
    ' | Amount: ' . $amount,
    'info',
    $user_id
);

/* ZETUPAY REQUEST */

$data = [
    "phoneNumber" => $phone,
    "amount" => (int)$amount,
    // ZetuPay uses reference as the actual Centipede Paybill account number.
    "reference" => $accountNumber,
    // D-LINK's unique payment reference is returned in identifier.
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

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

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

/* LOG RESPONSE */

file_put_contents(
    "zetupay_sub_log.txt",
    date('c') .
    " HTTP: " . $httpCode .
    " RESPONSE: " . $response . PHP_EOL,
    FILE_APPEND
);

/* SUCCESSFUL STK REQUEST */

if (
    $httpCode == 202 &&
    isset($result['success']) &&
    $result['success'] === true &&
    isset($result['data']) &&
    !empty($result['data']['paymentKey'])
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
        'ZetuPay subscription STK sent',
        'Reference: ' . $reference,
        'success',
        $user_id
    );

    echo json_encode([
    "success" => true,
    "http_code" => $httpCode,
    "transaction_id" => $transactionId,
    "payment_key" => $paymentData['paymentKey'],
    "reference" => $reference,
    "message" => "STK push sent. Check your phone."
]);

} else {
$failureReason = '';

foreach (['message', 'resultDesc', 'resultDescription', 'description', 'error'] as $field) {
    if (
        isset($result[$field]) &&
        is_string($result[$field]) &&
        trim($result[$field]) !== ''
    ) {
        $failureReason = trim($result[$field]);
        break;
    }
}

if ($failureReason === '') {
    $failureReason = 'ZetuPay could not initiate payment (HTTP ' . $httpCode . ').';
}

$saveFailure = $conn->prepare("
    UPDATE payments
    SET status = 'failed', failure_reason = ?
    WHERE reference = ?
    AND user_id = ?
    AND status = 'pending'
");

$saveFailure->bind_param(
    "ssi",
    $failureReason,
    $reference,
    $user_id
);

$saveFailure->execute();

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

exit;

?>
