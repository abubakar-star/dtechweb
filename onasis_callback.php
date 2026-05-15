<?php

http_response_code(200);

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
        "sender_id" => "TalkSasa",
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

/* SUCCESS PAYMENT */
if (
    isset($data['status']) &&
    $data['status'] === 'success' &&
    isset($data['reference'])
) {

    $reference = $data['reference'];

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

    /*
    =========================================
    FETCH USER ID FROM PAYMENTS TABLE
    =========================================
    */

    $payStmt = $conn->prepare(
        "SELECT user_id, invoice_number
         FROM payments
         WHERE reference = ?"
    );

    $payStmt->bind_param("s", $reference);

    $payStmt->execute();

    $payRes = $payStmt->get_result();

    if ($payRes->num_rows > 0) {

        $payment = $payRes->fetch_assoc();

        $userId = $payment['user_id'];

        $invoiceNumber = $payment['invoice_number'];

/*
=========================================
FETCH USER PHONE
=========================================
*/

$userPhone = '';
$userName = 'Customer';

$userPhoneStmt = $conn->prepare(
    "SELECT phone_number, first_name
     FROM users
     WHERE id = ?"
);

$userPhoneStmt->bind_param("i", $userId);

$userPhoneStmt->execute();

$userPhoneRes = $userPhoneStmt->get_result();

if ($userPhoneRes->num_rows > 0) {

    $userData = $userPhoneRes->fetch_assoc();

    $userPhone = $userData['phone_number'];

    if (!empty($userData['first_name'])) {
        $userName = $userData['first_name'];
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
                        created_at = '$newCreatedAt'
                     WHERE id = $userId"
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
                        created_at = NOW()
                     WHERE id = $userId"
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
        "Hello $userName, your payment has been received successfully. " .
        "Invoice: $invoiceNumber. " .
        "M-PESA Ref: $mpesa_receipt. " .
        "Thank you for choosing our service.";

    sendSMS($userPhone, $smsMessage);
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
