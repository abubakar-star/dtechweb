<?php

session_start();

if(
!isset($_SESSION['otp_verified'])
||
!$_SESSION['otp_verified']
){
    die("Unauthorized");
}

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$dbuser = $_ENV['MYSQLUSER'];
$dbpass = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $dbuser, $dbpass, $dbname, $port);

$username = $_POST['username'];

$password = trim($_POST['password']);


// HASH PASSWORD
$hashed = password_hash(
$password,
PASSWORD_DEFAULT
);

$stmt = $conn->prepare(
"UPDATE users
SET password=?,
reset_otp=NULL,
otp_expiry=NULL
WHERE username=?"
);

$stmt->bind_param(
"ss",
$hashed,
$username
);

$stmt->execute();


// AUTO LOGIN
$get = $conn->prepare(
"SELECT id, username FROM users WHERE username=?"
);

$get->bind_param("s", $username);

$get->execute();

$user = $get->get_result()->fetch_assoc();

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

echo "success";