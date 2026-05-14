<?php

header('Content-Type: application/json');

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

$reference = $_GET['reference'] ?? '';

if (empty($reference)) {

    echo json_encode([
        "success" => false,
        "message" => "Reference missing"
    ]);
    exit;
}

$stmt = $conn->prepare("
  SELECT status, failure_reason
FROM payments
WHERE reference = ?
LIMIT 1
");

$stmt->bind_param("s", $reference);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $payment = $result->fetch_assoc();

  echo json_encode([
    "success" => true,
    "status" => $payment['status'],
    "failure_reason" => $payment['failure_reason']
]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Payment not found"
    ]);
}
?>
