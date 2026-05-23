<?php

session_start();

include 'includes/logger.php';

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

$stmt = $conn->prepare(
"UPDATE users
SET password=?,
reset_otp=NULL,
otp_expiry=NULL
WHERE username=?"
);

$stmt->bind_param(
"ss",
$password,
$username
);

if($stmt->execute()){

    // AUTO LOGIN
    $get = $conn->prepare(
    "SELECT id, username FROM users WHERE username=?"
    );

    $get->bind_param("s", $username);

    $get->execute();

    $user = $get->get_result()->fetch_assoc();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    createLog(
        $conn,
        $user['id'],
        null,
        'security',
        'password_changed',
        'User changed account password',
        'success'
    );

    echo "success";

}else{

    createLog(
        $conn,
        null,
        null,
        'security',
        'password_change_failed',
        'Failed password change attempt for username: '.$username,
        'error'
    );

    echo "failed";
}


// AUTO LOGIN
$get = $conn->prepare(
"SELECT id, username FROM users WHERE username=?"
);

$get->bind_param("s", $username);

$get->execute();

$user = $get->get_result()->fetch_assoc();

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

createLog(
    $conn,
    'security',
    'password_changed',
    'User changed account password',
    'success',
    $user['id']
);

echo "success";
