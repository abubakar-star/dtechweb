<?php

session_start();

require_once 'includes/logger.php';

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$dbuser = $_ENV['MYSQLUSER'];
$dbpass = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $dbuser, $dbpass, $dbname, $port);

$username = $_POST['username'];
$otp = $_POST['otp'];

$stmt = $conn->prepare(
"SELECT * FROM users
WHERE username=?
AND reset_otp=?
AND otp_expiry > NOW()"
);

$stmt->bind_param("ss", $username, $otp);

$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows === 1){

    $_SESSION['otp_verified'] = true;
    $_SESSION['reset_user'] = $username;

    createLog(
        $conn,
        'security',
        'OTP verification successful',
        'User successfully verified OTP for password reset: ' . $username,
        'info',
        null
    );

    echo "success";

}else{

    createLog(
        $conn,
        'security',
        'Invalid OTP attempt',
        'Failed OTP verification attempt for username: ' . $username,
        'warning',
        null
    );

    echo "invalid";

}
