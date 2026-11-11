<?php

include 'onasis_config.php';
include 'includes/logger.php';

/*
=========================================
TALKSASA SMS
UNCHANGED
=========================================
*/

function sendSMS($phone, $message)
{
    // KEEP YOUR ORIGINAL TALKSASA TOKEN LINE HERE UNCHANGED.
    $token = "3126|cEo2LuIPqQCnEdZ9bma2IFDUBUt8YPqu6X8Gm2god1dcfd0b";

    $url = "https://bulksms.talksasa.com/api/v3/sms/send";

    $data = [
        "recipient" => $phone,
        "sender_id" => "TALKSASA",
        "message" => $message
    ];

    $payload = json_encode($data);

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);

    $error = curl_error($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    file_put_contents(
        "onasis_log.txt",
        "SMS CODE: $httpCode\nERROR: $error\nRESPONSE: $response\n\n",
        FILE_APPEND
    );

    curl_close($ch);
}

/*
=========================================
READ RAW ZETUPAY WEBHOOK
=========================================
*/

$raw = file_get_contents("php://input");

$signatureHeader = $_SERVER['HTTP_X_ZETUPAY_SIGNATURE'] ?? '';

/*
=========================================
VERIFY ZETUPAY SIGNATURE
=========================================
*/

if (empty($ZETUPAY_SECRET_KEY)) {
    http_response_code(500);
    exit("Gateway configuration missing");
}

$parts = [];

foreach (explode(',', $signatureHeader) as $part) {

    $pair = explode('=', trim($part), 2);

    if (count($pair) === 2) {
        $parts[$pair[0]] = $pair[1];
    }
}

$timestamp = $parts['t'] ?? '';
$signature = $parts['v1'] ?? '';

if (
    empty($timestamp) ||
    empty($signature) ||
    !ctype_digit((string)$timestamp) ||
    !preg_match('/^[a-f0-9]{64}$/i', $signature)
) {
    http_response_code(401);
    exit("Invalid webhook signature");
}

/* CHECK TIMESTAMP */

if (abs(time() - (int)$timestamp) > 300) {
    http_response_code(401);
    exit("Expired webhook");
}

/* CALCULATE EXPECTED SIGNATURE */

$expectedSignature = hash_hmac(
    'sha256',
    $timestamp . "." . $raw,
    $ZETUPAY_SECRET_KEY
);

if (!hash_equals(
    strtolower($expectedSignature),
    strtolower($signature)
)) {
    http_response_code(401);
    exit("Signature verification failed");
}

/*
=========================================
DECODE VERIFIED WEBHOOK
=========================================
*/

$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    exit("Invalid JSON");
}

/*
=========================================
DATABASE CONNECTION
=========================================
*/

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
    http_response_code(500);
    exit("Database connection failed");
}

$conn->set_charset("utf8mb4");

/*
=========================================
LOG VERIFIED CALLBACK
=========================================
*/

createLog(
    $conn,
    'payment',
    'ZetuPay callback received',
    'Verified payment callback received',
    'info'
);

/*
=========================================
HANDLE SUBSCRIPTION EVENTS SEPARATELY
=========================================
*/

if (isset($data['event'])) {

    createLog(
        $conn,
        'payment',
        'ZetuPay subscription event',
        'Event: ' . $data['event'],
        'info'
    );

    http_response_code(200);
    exit("OK");
}

/*
=========================================
ONLY PROCESS SUCCESSFUL PAYMENTS
=========================================
*/

if (
    ($data['status'] ?? '') !== 'success' ||
    empty($data['reference']) ||
    empty($data['identifier']) ||
    empty($data['waveTransactionId']) ||
    empty($data['receiptNumber'])
) {
    http_response_code(200);
    exit("Payment not confirmed");
}

// ZetuPay "reference" is the Centipede Paybill account number.
$accountReference = trim((string)$data['reference']);

// ZetuPay "identifier" carries our unique D-LINK payment reference.
$reference = trim((string)$data['identifier']);

$receipt = $data['receiptNumber'];
$waveTransactionId = $data['waveTransactionId'];
$paymentAmount = (float)($data['amount'] ?? 0);

/*
=========================================
BEGIN DATABASE TRANSACTION
=========================================
*/

$conn->begin_transaction();

try {

    /*
    FETCH PAYMENT AND LOCK RECORD
    */

    $stmt = $conn->prepare("
        SELECT user_id, invoice_number, amount, status
        FROM payments
        WHERE reference = ?
        FOR UPDATE
    ");

    $stmt->bind_param("s", $reference);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Payment reference not found");
    }

    $payment = $result->fetch_assoc();

    $userId = (int)$payment['user_id'];
    $amount = (float)$payment['amount'];
    $invoiceNumber = $payment['invoice_number'];

    /*
    DUPLICATE PAYMENT PROTECTION
    */

    if ($payment['status'] === 'completed') {

        $conn->commit();

        createLog(
            $conn,
            'payment',
            'Duplicate callback ignored',
            'D-LINK reference: ' . $reference .
            ' | Account: ' . $accountReference .
            ' | ZetuPay ID: ' . $waveTransactionId,
            'info',
            $userId
        );

        http_response_code(200);
        exit("Already processed");
    }

    /*
    VERIFY PAYMENT AMOUNT
    */

    if (abs($amount - $paymentAmount) > 0.001) {
        throw new Exception("Payment amount mismatch");
    }

    /*
    FETCH AND LOCK USER
    */

    $userStmt = $conn->prepare("
        SELECT phone_number, username, created_at, account_number
        FROM users
        WHERE id = ?
        FOR UPDATE
    ");

    $userStmt->bind_param("i", $userId);
    $userStmt->execute();

    $userResult = $userStmt->get_result();

    if ($userResult->num_rows === 0) {
        throw new Exception("Customer not found");
    }

    $userData = $userResult->fetch_assoc();

    $userPhone = trim($userData['phone_number']);
    $userName = $userData['username'] ?: 'Customer';

    // Ensure the Paybill account reference belongs to this D-LINK customer.
    if (trim((string)$userData['account_number']) !== $accountReference) {
        throw new Exception("Centipede account reference does not match the D-LINK customer");
    }

    /*
    FORMAT CUSTOMER PHONE
    */

    if (substr($userPhone, 0, 1) === "0") {
        $userPhone = "254" . substr($userPhone, 1);
    }

    /*
    UPDATE PAYMENT RECORD
    */

    $paymentMethod = "M-PESA";

    $updatePayment = $conn->prepare("
        UPDATE payments
        SET
            status = 'completed',
            transaction_id = ?,
            payment_method = ?,
            payment_date = NOW()
        WHERE reference = ?
    ");

    $updatePayment->bind_param(
        "sss",
        $receipt,
        $paymentMethod,
        $reference
    );

    if (!$updatePayment->execute()) {
        throw new Exception("Payment update failed");
    }

    /*
    CALCULATE SUBSCRIPTION EXPIRY
    */

    $createdAt = new DateTime($userData['created_at']);

    $today = new DateTime();

    $expiry = clone $createdAt;

    $expiry->modify('+30 days');

    /*
    RENEW ACTIVE SUBSCRIPTION
    */

    if ($expiry >= $today) {

        $newCreatedAt = $expiry->format('Y-m-d H:i:s');

        $subscriptionStmt = $conn->prepare("
            UPDATE users
            SET status = 'active', created_at = ?
            WHERE id = ?
        ");

        $subscriptionStmt->bind_param(
            "si",
            $newCreatedAt,
            $userId
        );

        $subscriptionStmt->execute();

        $subscriptionMessage = "Existing subscription extended by 30 days";

    }

    /*
    REACTIVATE EXPIRED SUBSCRIPTION
    */

    else {

        $subscriptionStmt = $conn->prepare("
            UPDATE users
            SET status = 'active', created_at = NOW()
            WHERE id = ?
        ");

        $subscriptionStmt->bind_param("i", $userId);

        $subscriptionStmt->execute();

        $subscriptionMessage = "Expired subscription reactivated";
    }

    /*
    COMMIT DATABASE CHANGES
    */

    $conn->commit();

    /*
    LOG PAYMENT SUCCESS
    */

    createLog(
        $conn,
        'payment',
        'ZetuPay payment completed',
        'D-LINK reference: ' . $reference .
        ' | Centipede account: ' . $accountReference .
        ' | Receipt: ' . $receipt,
        'success',
        $userId
    );

    createLog(
        $conn,
        'subscription',
        'Subscription updated',
        $subscriptionMessage,
        'success',
        $userId
    );

    /*
    CUSTOMER PAYMENT SMS
    */

    if (!empty($userPhone)) {

        $smsMessage =
            "PAYMENT RECEIVED\n" .
            "User: $userName\n" .
            "Amount: KES $amount\n" .
            "Invoice: $invoiceNumber\n" .
            "M-PESA Ref: $receipt\n\n" .
            "From D-LINK NETWORK INC.";

        sendSMS($userPhone, $smsMessage);

        createLog(
            $conn,
            'notification',
            'Customer SMS sent',
            'Payment confirmation SMS sent',
            'info',
            $userId
        );

        /*
        ADMIN PAYMENT ALERT
        */

        $adminQuery = $conn->query("
            SELECT phone_number
            FROM admin_contacts
            LIMIT 1
        ");

        if ($adminQuery && $adminQuery->num_rows > 0) {

            $admin = $adminQuery->fetch_assoc();

            $adminPhone = trim($admin['phone_number']);

            if (substr($adminPhone, 0, 1) === "0") {
                $adminPhone = "254" . substr($adminPhone, 1);
            }

            if (!empty($adminPhone)) {

                $adminMessage =
                    "PAYMENT RECEIVED\n" .
                    "User: $userName\n" .
                    "Amount: KES $amount\n" .
                    "Invoice: $invoiceNumber\n" .
                    "M-PESA Ref: $receipt";

                sendSMS($adminPhone, $adminMessage);

                createLog(
                    $conn,
                    'notification',
                    'Admin SMS sent',
                    'Admin payment alert sent',
                    'info',
                    $userId
                );
            }
        }
    }

    file_put_contents(
        "zetupay_callback_log.txt",
        date('c') .
        " SUCCESS: $reference | Account: $accountReference | $waveTransactionId\n",
        FILE_APPEND
    );

    http_response_code(200);
    echo "OK";

} catch (Throwable $e) {

    $conn->rollback();

    createLog(
        $conn,
        'payment',
        'ZetuPay callback processing failed',
        $e->getMessage(),
        'error'
    );

    file_put_contents(
        "zetupay_callback_log.txt",
        date('c') .
        " ERROR: " . $e->getMessage() . "\n",
        FILE_APPEND
    );

    http_response_code(500);
    echo "Processing failed";
}

$conn->close();

?>
