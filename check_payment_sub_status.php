<?php

header('Content-Type: application/json');
include 'includes/logger.php';

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {

createLog(
    $conn,
    'database',
    'database_connection_failed',
    'Database connection failed in check_payment_status.php',
    'critical'
);

    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

$reference = $_GET['reference'] ?? '';

if (empty($reference)) {

createLog(
    $conn,
    'payment',
    'missing_payment_reference',
    'Payment status check attempted without reference',
    'warning'
);

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

    createLog(
    $conn,
    'payment',
    'payment_status_checked',
    'Payment status checked for reference: '.$reference,
    'info'
);

  echo json_encode([
    "success" => true,
    "status" => $payment['status'],
    "failure_reason" => $payment['failure_reason']
]);

} else {

createLog(
    $conn,
    'payment',
    'payment_pending',
    'Payment still pending for reference: '.$reference,
    'info'
);

    echo json_encode([
        "success" => false,
        "message" => "Payment not found"
    ]);
}
?>
