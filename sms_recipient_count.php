<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host,$username,$password,$dbname,$port);

if($conn->connect_error){
    exit(json_encode([
        "success"=>false,
        "count"=>0
    ]));
}

$group = $_POST['group'] ?? '';
$package = (int)($_POST['package_id'] ?? 0);

$count = 0;

switch($group){

    case "active":

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE status='active'
        ");

        break;

    case "expired":

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE Expiry < CURDATE()
        ");

        break;

    case "expiring3":

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE Expiry BETWEEN CURDATE()
            AND DATE_ADD(CURDATE(),INTERVAL 3 DAY)
        ");

        break;

    case "package":

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE status='active'
            AND package_id=?
        ");

        $stmt->bind_param("i",$package);

        break;

    default:

        echo json_encode([
            "success"=>true,
            "count"=>0
        ]);

        exit;
}

$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

echo json_encode([
    "success"=>true,
    "count"=>$count
]);