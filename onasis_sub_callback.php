<?php

http_response_code(200);

include 'includes/logger.php';

/*
=========================================
SEND SMS USING TALKSASA API
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

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

$raw = file_get_contents("php://input");

file_put_contents(
    "onasis_log.txt",
    date('Y-m-d H:i:s') . "\n" . $raw . "\n\n",
    FILE_APPEND
);

$data = json_decode($raw, true);

file_put_contents(
    "onasis_secondlog.txt",
    "PARSED STATUS: " . ($data['status'] ?? 'NO STATUS') . "\n\n",
    FILE_APPEND
);

createLog(
    $conn,
    'payment',
    'Onasis callback received',
    'Incoming callback received from payment gateway',
    'info'
);

/* SUCCESS PAYMENT */
if (
    isset($data['status']) &&
    $data['status'] === 'success' &&
    isset($data['reference'])
) {

    $reference = $data['reference'];

    createLog(
    $conn,
    'payment',
    'Payment success callback',
    'Reference: ' . $reference,
    'success'
);

    $mpesa_receipt = isset($data['mpesa_receipt'])
        ? $data['mpesa_receipt']
        : '';

    $payment_method = "M-PESA";

    /* UPDATE PAYMENT TABLE */
    $stmt = $conn->prepare(
        "UPDATE payments
         SET
            status = 'completed',
            transaction_id = ?,
            payment_method = ?,
            payment_date = NOW()
         WHERE reference = ?"
    );

    $stmt->bind_param(
        "sss",
        $mpesa_receipt,
        $payment_method,
        $reference
    );

    $stmt->execute();

    if ($stmt->affected_rows > 0) {

    createLog(
        $conn,
        'payment',
        'Payment updated',
        'Payment marked completed: ' . $reference,
        'success'
    );

} else {

    createLog(
        $conn,
        'payment',
        'Payment update failed',
        'No payment record updated for: ' . $reference,
        'warning'
    );

}

    /*
    =========================================
    FETCH USER ID FROM PAYMENTS TABLE
    =========================================
    */

    $payStmt = $conn->prepare(
        "SELECT 
    user_id,
    package_id,
    invoice_number,
    amount
 FROM payments
 WHERE reference = ?"
    );

    $payStmt->bind_param("s", $reference);

    $payStmt->execute();

    $payRes = $payStmt->get_result();

    if ($payRes->num_rows > 0) {

        $payment = $payRes->fetch_assoc();

        $userId = $payment['user_id'];

        $packageId = $payment['package_id'];

        createLog(
    $conn,
    'payment',
    'Payment linked to user',
    'Reference: ' . $reference,
    'info',
    $userId
);

        $invoiceNumber = $payment['invoice_number'];

        $amount = $payment['amount'];

/*
=========================================
FETCH USER PHONE
=========================================
*/

$userPhone = '';
$userName = 'Customer';

$userPhoneStmt = $conn->prepare(
    "SELECT phone_number, username
     FROM users
     WHERE id = ?"
);

$userPhoneStmt->bind_param("i", $userId);

$userPhoneStmt->execute();

$userPhoneRes = $userPhoneStmt->get_result();

if ($userPhoneRes->num_rows > 0) {

    $userData = $userPhoneRes->fetch_assoc();

    $userPhone = trim($userData['phone_number']);

if (substr($userPhone, 0, 1) === "0") {
    $userPhone = "254" . substr($userPhone, 1);
}

    if (!empty($userData['username'])) {
        $userName = $userData['username'];
    }
}


        /*
        =========================================
        FETCH CURRENT USER STATUS
        =========================================
        */

        $userRes = $conn->query(
            "SELECT status, created_at
             FROM users
             WHERE id = $userId"
        );

        if ($userRes && $userRes->num_rows > 0) {

            $user = $userRes->fetch_assoc();

            $createdAt = new DateTime($user['created_at']);

            $today = new DateTime();

            /*
            =========================================
            CALCULATE CURRENT EXPIRY
            =========================================
            */

            $expiry = clone $createdAt;

            $expiry->modify('+30 days');

            /*
            =========================================
            IF USER PAYS BEFORE EXPIRY
            =========================================
            */

            if ($expiry >= $today) {

                $newCreatedAt =
                    $expiry->format('Y-m-d H:i:s');

                $conn->query(
    "UPDATE users
     SET
        status = 'active',
        package_id = $packageId,
        created_at = '$newCreatedAt'
     WHERE id = $userId"
);
                

                createLog(
    $conn,
    'subscription',
    'Subscription renewed',
    'Existing subscription extended by 30 days',
    'success',
    $userId
);

            }

            /*
            =========================================
            IF USER PAYS AFTER EXPIRY
            =========================================
            */

            else {

              $conn->query(
    "UPDATE users
     SET
        status = 'active',
        package_id = $packageId,
        created_at = NOW()
     WHERE id = $userId"
);

                createLog(
    $conn,
    'subscription',
    'Subscription activated',
    'Expired subscription reactivated',
    'success',
    $userId
);

            }

            file_put_contents(
                "onasis_log.txt",
                "UPDATED USER SUBSCRIPTION: USER ID $userId | REF $reference\n\n",
                FILE_APPEND
            );
        }
    }
/*
=========================================
SEND PAYMENT SUCCESS SMS
=========================================
*/

if (!empty($userPhone)) {

$smsMessage =
        "Payment Confirmed.\n" .
        "Account: $userName\n" .
        "Amount: KES $amount\n" .
        "Invoice: $invoiceNumber\n" .
        "M-PESA Ref: $mpesa_receipt\n" .
        "\n" .
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

$adminQuery = $conn->query(
    "SELECT phone_number
     FROM admin_contacts
     LIMIT 1"
);

if ($adminQuery && $adminQuery->num_rows > 0) {

    $admin = $adminQuery->fetch_assoc();

    $adminPhone = trim($admin['phone_number']);

    // Format admin number
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
        "M-PESA Ref: $mpesa_receipt";

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
    file_put_contents(
        "onasis_log.txt",
        "UPDATED SUCCESS PAYMENT: $reference\n\n",
        FILE_APPEND
    );
}

/* FAILED PAYMENT */
if (
    isset($data['status']) &&
    $data['status'] === 'failed' &&
    isset($data['reference'])
) {

    $reference = $data['reference'];

     $failure_reason = isset($data['result_desc'])
        ? $data['result_desc']
        : 'Unknown error';
    
    createLog(
    $conn,
    'payment',
    'Payment failed',
    'Reference: ' .
    $reference .
    ' | Reason: ' .
    $failure_reason,
    'error'
);

   

    $stmt = $conn->prepare(
        "UPDATE payments
         SET
            status = 'failed',
            failure_reason = ?
         WHERE reference = ?"
    );

    $stmt->bind_param(
        "ss",
        $failure_reason,
        $reference
    );

    $stmt->execute();

    file_put_contents(
        "onasis_log.txt",
        "FAILED PAYMENT: $reference | $failure_reason\n\n",
        FILE_APPEND
    );
}

echo "OK";
