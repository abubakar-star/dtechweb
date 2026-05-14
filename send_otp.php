<?php

session_start();

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$dbuser = $_ENV['MYSQLUSER'];
$dbpass = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $dbuser, $dbpass, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed");
}

$username = trim($_POST['username']);

$stmt = $conn->prepare(
    "SELECT id, phone FROM users WHERE username=?"
);

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
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


// FORMAT PHONE
$phone = trim($user['phone']);

if (substr($phone, 0, 1) === "0") {
    $phone = "254" . substr($phone, 1);
}


// SMS MESSAGE
$message = "Your D-LINK NETWORK OTP is: $otp";


// TALKSASA API
$data = [
    "recipient" => $phone,
    "sender_id" => "TALKSASA",
    "message" => $message
];

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


// DEBUGGING
if ($error) {
    die("cURL Error: " . $error);
}

echo "success";
