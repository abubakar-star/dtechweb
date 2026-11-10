<?php
/*
 * ZetuPay webhook endpoint for extra-charge payments.
 * Keep this endpoint URL configured in the ZetuPay dashboard:
 * https://dtechweb.onrender.com/onasis_sub_callback.php
 */

require_once __DIR__ . '/onasis_config_extra.php';
include_once __DIR__ . '/includes/logger.php';

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
