```php
<?php

include 'onasis_config_change.php';
include 'includes/logger.php';

/*
=========================================
SEND SMS USING TALKSASA API
UNCHANGED
=========================================
*/

function sendSMS($phone, $message)
{
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

    if (curl_errno($ch)) {
        file_put_contents(
            "onasis_log.txt",
            "SMS ERROR: " . curl_error($ch) . "\n\n",
            FILE_APPEND
        );
    } else {
        file_put_contents(
            "onasis_log.txt",
            "SMS RESPONSE: " . $response . "\n\n",
            FILE_APPEND
        );
    }

    curl_close($ch);
}

/*
=========================================
READ ZETUPAY WEBHOOK
=========================================
*/

$raw = file_get_contents("php://input");

file_put_contents(
    "onasis_log.txt",
    date('Y-m-d H:i:s') .
    "\nZETUPAY PACKAGE CALLBACK:\n" .
    $raw . "\n\n",
    FILE_APPEND
);

/*
=========================================
VERIFY ZETUPAY SIGNATURE
=========================================
*/

if (empty($ZETUPAY_SECRET_KEY)) {

    http_response_code(500);
    exit("Gateway configuration missing");
}

$signatureHeader = $_SERVER['HTTP_X_ZETUPAY_SIGNATURE'] ?? '';

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

/* CALCULATE SIGNATURE */

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
LOG CALLBACK
=========================================
*/

createLog(
    $conn,
    'payment',
    'ZetuPay package callback received',
    'Verified callback received',
    'info'
);

/*
=========================================
CHECK PAYMENT RESPONSE
=========================================
*/

$status = $data['status'] ?? '';

// ZetuPay sends the Centipede account number in reference,
// and the unique D-LINK payment reference in identifier.
$accountReference = trim((string)($data['reference'] ?? ''));
$reference = trim((string)($data['identifier'] ?? ''));

if ($accountReference === '' || $reference === '') {

    http_response_code(400);
    exit("Missing payment reference");
}

/*
=========================================
HANDLE FAILED PAYMENT
=========================================
*/

if ($status === 'failed') {

    $failureReason = $data['result_desc']
        ?? $data['message']
        ?? 'Payment failed';

    $stmt = $conn->prepare("
        UPDATE payments
        SET
            status = 'failed',
            failure_reason = ?
        WHERE reference = ?
        AND status = 'pending'
    ");

    $stmt->bind_param(
        "ss",
        $failureReason,
        $reference
    );

    $stmt->execute();

    createLog(
        $conn,
        'payment',
        'ZetuPay package payment failed',
        'Reference: ' . $reference .
        ' | Reason: ' . $failureReason,
        'error'
    );

    file_put_contents(
        "onasis_log.txt",
        "FAILED PAYMENT: $reference\n\n",
        FILE_APPEND
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
    $status !== 'success' ||
    empty($accountReference) ||
    empty($data['waveTransactionId']) ||
    empty($data['receiptNumber']) ||
    !isset($data['amount'])
) {

    http_response_code(200);
    exit("Payment not confirmed");
}

$receipt = $data['receiptNumber'];

$waveTransactionId = $data['waveTransactionId'];

$paymentAmount = (float)$data['amount'];

/*
=========================================
BEGIN DATABASE TRANSACTION
=========================================
*/

$conn->begin_transaction();

try {

    /*
    FETCH PAYMENT RECORD
    */

    $stmt = $conn->prepare("
        SELECT
            user_id,
            package_id,
            invoice_number,
            amount,
            status
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

    $packageId = (int)$payment['package_id'];

    $invoiceNumber = $payment['invoice_number'];

    $amount = (float)$payment['amount'];

    /*
    DUPLICATE CALLBACK PROTECTION
    */

    if ($payment['status'] === 'completed') {

        $conn->commit();

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
    FETCH CUSTOMER DETAILS
    */

    $userStmt = $conn->prepare("
        SELECT
            phone_number,
            username,
            package_id,
            account_number
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

    $currentPackageId = (int)$userData['package_id'];

    if (trim((string)$userData['account_number']) !== $accountReference) {
        throw new Exception("Centipede account reference does not match the D-LINK customer");
    }

    /*
    VERIFY PACKAGE CHANGE
    */

    if ($packageId < 1) {

        throw new Exception("Invalid package ID");
    }

    if ($currentPackageId === $packageId) {

        throw new Exception("Selected package is already assigned to customer");
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
    =========================================
    PACKAGE CHANGE
    WAITING FOR ADMIN VERIFICATION
    =========================================
    */

    $packageUpdate = $conn->prepare("
        UPDATE users
        SET
            status = 'queued',
            updating = 'yes',
            package_id = ?,
            package_queue_popup = 0,
            created_at = NULL
        WHERE id = ?
    ");

    $packageUpdate->bind_param(
        "ii",
        $packageId,
        $userId
    );

    if (!$packageUpdate->execute()) {

        throw new Exception("Package update failed");
    }

    /*
    LOG PACKAGE CHANGE
    */

    createLog(
        $conn,
        'subscription',
        'Package change awaiting verification',
        'Payment received. Package ID: ' .
        $packageId .
        '. Waiting for administrator verification.',
        'info',
        $userId
    );

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
        'ZetuPay package payment completed',
        'D-LINK reference: ' . $reference .
        ' | Centipede account: ' . $accountReference .
        ' | Receipt: ' . $receipt .
        ' | Transaction: ' . $waveTransactionId,
        'success',
        $userId
    );

    /*
    =========================================
    SEND CUSTOMER PAYMENT SUCCESS SMS
    =========================================
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
        =========================================
        SEND ADMIN PAYMENT ALERT SMS
        =========================================
        */

        $adminPhone = null;

        $adminQuery = $conn->query("
            SELECT phone_number
            FROM admin_contacts
            LIMIT 1
        ");

        if (
            $adminQuery &&
            $adminQuery->num_rows > 0
        ) {

            $admin = $adminQuery->fetch_assoc();

            $adminPhone = trim($admin['phone_number']);

            if (substr($adminPhone, 0, 1) === "0") {

                $adminPhone =
                    "254" . substr($adminPhone, 1);
            }
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

    /*
    LOG SUCCESS
    */

    file_put_contents(
        "onasis_log.txt",
        "PACKAGE CHANGE PAYMENT SUCCESS: " .
        $reference .
        " | USER: " . $userId .
        " | PACKAGE: " . $packageId .
        "\n\n",
        FILE_APPEND
    );

    http_response_code(200);

    echo "OK";

} catch (Throwable $e) {

    $conn->rollback();

    createLog(
        $conn,
        'payment',
        'ZetuPay package callback processing failed',
        $e->getMessage(),
        'error'
    );

    file_put_contents(
        "onasis_log.txt",
        "CALLBACK ERROR: " .
        $e->getMessage() .
        "\nREFERENCE: " .
        $reference .
        "\n\n",
        FILE_APPEND
    );

    http_response_code(500);

    echo "Processing failed";
}

$conn->close();

?>

