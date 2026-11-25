<?php
/*
 * ZetuPay webhook endpoint for extra-charge payments.
 * Keep this endpoint URL configured in the ZetuPay dashboard:
 * https://dtechweb.onrender.com/onasis_sub_callback.php
 */

require_once __DIR__ . '/onasis_config_extra.php';
include_once __DIR__ . '/includes/logger.php';

/*
=========================================
TALKSASA SMS FUNCTION
=========================================
*/

function sendExtraChargeSMS($phone, $message)
{
    // Paste the same Talksasa API token used in onasis_callback.php.
    $token = "3126|cEo2LuIPqQCnEdZ9bma2IFDUBUt8YPqu6X8Gm2god1dcfd0b";

    $url = "https://bulksms.talksasa.com/api/v3/sms/send";

    $data = [
        "recipient" => $phone,
        "sender_id" => "TALKSASA",
        "message" => $message
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);

    $error = curl_error($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    file_put_contents(
        __DIR__ . "/onasis_log.txt",
        date('c') .
        "\nSMS CODE: " . $httpCode .
        "\nERROR: " . $error .
        "\nRESPONSE: " . $response .
        "\n\n",
        FILE_APPEND
    );

    curl_close($ch);

    return (
        $response !== false &&
        $httpCode >= 200 &&
        $httpCode < 300
    );
}


/*
=========================================
KENYAN PHONE NUMBER FORMATTING
=========================================
*/

function formatExtraChargePhone($phone)
{
    $phone = preg_replace('/\s+/', '', trim($phone));

    if (strpos($phone, '+') === 0) {
        $phone = substr($phone, 1);
    } elseif (strpos($phone, '0') === 0) {
        $phone = '254' . substr($phone, 1);
    }

    return $phone;
}

http_response_code(200);

/* Read and verify the raw ZetuPay webhook payload. */
$raw = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_ZETUPAY_SIGNATURE'] ?? '';

if (empty($ZETUPAY_SECRET_KEY)) {
    http_response_code(500);
    exit('Gateway configuration missing');
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
    $timestamp === '' ||
    $signature === '' ||
    !ctype_digit((string)$timestamp) ||
    !preg_match('/^[a-f0-9]{64}$/i', $signature)
) {
    http_response_code(401);
    exit('Invalid webhook signature');
}

if (abs(time() - (int)$timestamp) > 300) {
    http_response_code(401);
    exit('Expired webhook');
}

$expectedSignature = hash_hmac(
    'sha256',
    $timestamp . '.' . $raw,
    $ZETUPAY_SECRET_KEY
);

if (!hash_equals(strtolower($expectedSignature), strtolower($signature))) {
    http_response_code(401);
    exit('Signature verification failed');
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid JSON');
}

/* Connect to the database only after signature verification. */
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}
$conn->set_charset('utf8mb4');

file_put_contents(
    'zetupay_extra_callback_log.txt',
    date('c') . ' ' . $raw . PHP_EOL,
    FILE_APPEND
);

$reference = trim((string)($data['reference'] ?? ''));
$status = strtolower((string)($data['status'] ?? ''));

if ($reference === '') {
    http_response_code(400);
    exit('Missing payment reference');
}

/* Look up and validate that this is an extra-charge payment. */
$lookup = $conn->prepare(
    "SELECT user_id, invoice_number, amount, payment_type, status
     FROM payments WHERE reference = ? LIMIT 1"
);
$lookup->bind_param('s', $reference);
$lookup->execute();
$paymentResult = $lookup->get_result();

if ($paymentResult->num_rows === 0) {
    http_response_code(200);
    exit('Payment reference not found');
}

$payment = $paymentResult->fetch_assoc();
if ($payment['payment_type'] !== 'extra_charge') {
    http_response_code(200);
    exit('Not an extra-charge payment');
}

if ($status === 'success') {
    $receipt = (string)($data['receiptNumber'] ?? '');
    $transactionId = (string)($data['waveTransactionId'] ?? '');

    /* Make webhook retries safe: only process a payment once. */
    if ($payment['status'] !== 'completed') {
        $updatePayment = $conn->prepare(
            "UPDATE payments
             SET status = 'completed', transaction_id = ?,
                 payment_method = 'M-PESA', payment_date = NOW()
             WHERE reference = ? AND status <> 'completed'"
        );
        $updatePayment->bind_param('ss', $transactionId, $reference);
        $updatePayment->execute();

        if ($updatePayment->affected_rows > 0) {
            $userId = (int)$payment['user_id'];

       $chargeQuery = $conn->prepare(
    "SELECT charge_name
     FROM extra_charges
     WHERE user_id = ? AND status = 'pending'"
);

$chargeQuery->bind_param('i', $userId);
$chargeQuery->execute();

$chargeResult = $chargeQuery->get_result();

$chargeNames = [];

while ($charge = $chargeResult->fetch_assoc()) {
    $chargeNames[] = strtoupper($charge['charge_name']);
}

$chargeName = !empty($chargeNames)
    ? implode(', ', $chargeNames)
    : 'EXTRA CHARGE';


$updateCharges = $conn->prepare(
    "UPDATE extra_charges
     SET status = 'paid', paid_at = NOW()
     WHERE user_id = ? AND status = 'pending'"
);

$updateCharges->bind_param('i', $userId);
$updateCharges->execute();

            createLog(
                $conn,
                'billing',
                'Extra charges paid',
                'ZetuPay confirmed payment. Reference: ' . $reference .
                ' | Receipt: ' . $receipt,
                'success',
                $userId
            );

/*
=========================================
GET CUSTOMER DETAILS
=========================================
*/

$amount = (float)$payment['amount'];

$invoiceNumber = (string)$payment['invoice_number'];

$customerQuery = $conn->prepare(
    "SELECT first_name, last_name, phone_number
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$customerQuery->bind_param('i', $userId);

$customerQuery->execute();

$customerResult = $customerQuery->get_result();

$userName = 'Customer';

$userPhone = '';

if ($customerResult && $customerResult->num_rows > 0) {

    $customer = $customerResult->fetch_assoc();

    $userName = trim(
        $customer['first_name'] . ' ' . $customer['last_name']
    );

    if ($userName === '') {
        $userName = 'Customer';
    }

    $userPhone = trim((string)$customer['phone_number']);
}


/*
=========================================
CUSTOMER PAYMENT SMS
=========================================
*/

$userPhone = formatExtraChargePhone($userPhone);

if ($userPhone !== '') {

    $customerSms =
        "{$chargeName} PAYMENT RECEIVED\n" .
        "Client: {$userName}\n" .
        "Amount: KES " . number_format($amount, 2) . "\n" .
        "Invoice: {$invoiceNumber}\n" .
        "M-PESA Ref: {$receipt}\n\n" .
        "From D-LINK NETWORK INC.";

    $customerSmsSent = sendExtraChargeSMS(
        $userPhone,
        $customerSms
    );

    createLog(
        $conn,
        'notification',
        $customerSmsSent ? 'Customer SMS sent' : 'Customer SMS failed',
        $customerSmsSent
            ? 'Extra-charge payment confirmation SMS sent.'
            : 'Customer SMS failed. Check onasis_log.txt.',
        $customerSmsSent ? 'info' : 'warning',
        $userId
    );

} else {

    createLog(
        $conn,
        'notification',
        'Customer SMS skipped',
        'Customer phone number is missing.',
        'warning',
        $userId
    );
}


/*
=========================================
ADMIN PAYMENT SMS
=========================================
*/

$adminQuery = $conn->query(
    "SELECT phone_number
     FROM admin_contacts
     LIMIT 1"
);

if ($adminQuery && $adminQuery->num_rows > 0) {

    $admin = $adminQuery->fetch_assoc();

    $adminPhone = trim((string)$admin['phone_number']);

    $adminPhone = formatExtraChargePhone($adminPhone);

    if ($adminPhone !== '') {

        $adminSms =
            "{$chargeName} PAYMENT RECEIVED\n" .
            "Client: {$userName}\n" .
            "Amount: KES " . number_format($amount, 2) . "\n" .
            "Invoice: {$invoiceNumber}\n" .
            "M-PESA Ref: {$receipt}";

        $adminSmsSent = sendExtraChargeSMS(
            $adminPhone,
            $adminSms
        );

        createLog(
            $conn,
            'notification',
            $adminSmsSent ? 'Admin SMS sent' : 'Admin SMS failed',
            $adminSmsSent
                ? 'Extra-charge payment alert sent to admin.'
                : 'Admin SMS failed. Check onasis_log.txt.',
            $adminSmsSent ? 'info' : 'warning',
            $userId
        );
    }

} else {

    createLog(
        $conn,
        'notification',
        'Admin SMS skipped',
        'No administrator phone number found in admin_contacts.',
        'warning',
        $userId
    );
}

        }
    }

    http_response_code(200);
    exit('OK');
}

if (in_array($status, ['failed', 'cancelled', 'canceled'], true)) {
    $reason = (string)($data['message'] ?? $data['resultDescription'] ?? 'Payment failed');
    $failed = $conn->prepare(
        "UPDATE payments SET status = 'failed', failure_reason = ?
         WHERE reference = ? AND status = 'pending'"
    );
    $failed->bind_param('ss', $reason, $reference);
    $failed->execute();

    createLog(
        $conn,
        'billing',
        'Extra charge payment failed',
        'Reference: ' . $reference . ' | Reason: ' . $reason,
        'warning',
        (int)$payment['user_id']
    );
}

http_response_code(200);
echo 'OK';
