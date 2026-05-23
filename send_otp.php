<?php

session_start();

include 'includes/logger.php';

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$dbuser = $_ENV['MYSQLUSER'];
$dbpass = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $dbuser, $dbpass, $dbname, $port);

if ($conn->connect_error) {

    createLog(
        $conn,
        'database',
        'connection_failed',
        'MySQL connection failed in send_otp.php',
        'critical'
    );

    die("Database connection failed");
}

$username = trim($_POST['username']);

$stmt = $conn->prepare(
    "SELECT id, phone_number FROM users WHERE username=?"
);

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    createLog(
        $conn,
        'otp',
        'user_not_found',
        "OTP request failed for username: $username",
        'warning'
    );

    die("User not found");
}

$user = $result->fetch_assoc();


// GENERATE OTP
$otp = rand(100000, 999999);

$expiry = date(
    "Y-m-d H:i:s",
    strtotime("+5 minutes")
);


// SAVE OTP
$update = $conn->prepare(
    "UPDATE users
     SET reset_otp=?, otp_expiry=?
     WHERE id=?"
);

$update->bind_param(
    "ssi",
    $otp,
    $expiry,
    $user['id']
);

$update->execute();

echo "OTP: " . $otp;

if($update->affected_rows > 0){

    createLog(
        $conn,
        'otp',
        'otp_saved',
        "OTP saved successfully for user ID {$user['id']}",
        'success',
        $user['id']
    );

}else{

    createLog(
        $conn,
        'otp',
        'otp_save_failed',
        "Failed to save OTP for user ID {$user['id']}",
        'error',
        $user['id']
    );

    die("Failed to save OTP");
}

createLog(
    $conn,
    'otp',
    'otp_generated',
    "OTP generated for user ID {$user['id']}",
    'info',
    $user['id']
);


// FORMAT PHONE
$phone = trim($user['phone_number']);

if (substr($phone, 0, 1) === "0") {
    $phone = "254" . substr($phone, 1);
}


// SMS MESSAGE
$message = "Your D-LINK NETWORK OTP is: $otp";

// ---------------- CLIENT SMS ----------------
$clientData = [
    "recipient" => $phone,
    "sender_id" => "TALKSASA",
    "message" => $message
];

// ---------------- GET ADMIN NUMBER ----------------
$adminQuery = $conn->query(
    "SELECT phone_number FROM admin_contacts LIMIT 1"
);

$adminPhone = null;

if ($adminQuery && $adminQuery->num_rows > 0) {
    $admin = $adminQuery->fetch_assoc();
    $adminPhone = $admin['phone_number'] ?? null;
}

// ---------------- ADMIN SMS ----------------
$adminMessage =
"OTP Request Alert\n"
. "User: $username\n"
. "Phone: $phone\n"
. "OTP: $otp";

// SEND FUNCTION
function sendSMS($data) {

    $ch = curl_init();

    curl_setopt(
        $ch,
        CURLOPT_URL,
        "https://bulksms.talksasa.com/api/v3/sms/send"
    );

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($data)
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer 3126|cEo2LuIPqQCnEdZ9bma2IFDUBUt8YPqu6X8Gm2god1dcfd0b",
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    $error = curl_error($ch);

    curl_close($ch);

    return [
        "response" => $response,
        "error" => $error
    ];
}

// SEND TO CLIENT
$clientResult = sendSMS($clientData);

if ($clientResult['error']) {

    createLog(
        $conn,
        'sms',
        'client_sms_failed',
        "OTP SMS failed for user ID {$user['id']}: {$clientResult['error']}",
        'error',
        $user['id']
    );

    die("cURL Error: " . $clientResult['error']);
}
createLog(
    $conn,
    'sms',
    'client_sms_sent',
    "OTP SMS sent successfully to user ID {$user['id']}",
    'info',
    $user['id']
);

// SEND TO ADMIN
if ($adminPhone) {

    $adminData = [
        "recipient" => $adminPhone,
        "sender_id" => "TALKSASA",
        "message" => $adminMessage
    ];

    $adminResult = sendSMS($adminData);

if ($adminResult['error']) {

    createLog(
        $conn,
        'sms',
        'admin_sms_failed',
        "Admin OTP alert SMS failed: {$adminResult['error']}",
        'error'
    );

    die("Admin SMS Error: " . $adminResult['error']);
}
}


// MASK PHONE NUMBER
$maskedPhone =
substr($phone, 0, 4)
. "****"
. substr($phone, -3);

createLog(
    $conn,
    'otp',
    'otp_sent',
    "OTP successfully sent for user ID {$user['id']}",
    'info',
    $user['id']
);

echo "success|" . $maskedPhone;
