<?php
session_start();

include 'includes/logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {

createLog(
    $conn,
    'security',
    'Unauthorized password request',
    'Attempt to request router password change without login',
    'warning'
);

    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

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
    'Database connection failed',
    $conn->connect_error,
    'critical'
);

    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}

$newPassword = $_POST['new_password'] ?? '';
if (strlen($newPassword) < 4) {
    createLog(
    $conn,
    'security',
    'Weak password request',
    'Password change request rejected: too short',
    'warning',
    $_SESSION['user_id']
);

    echo json_encode(['success' => false, 'message' => 'Password too short']);
    exit;
}

// Get router_id
$stmt = $conn->prepare("SELECT router_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$router_id = $res['router_id'] ?? 0;

$stmt = $conn->prepare("
  INSERT INTO router_password_requests (user_id, router_id, new_password, status)
  VALUES (?, ?, ?, 'pending')
");
$stmt->bind_param("iis", $_SESSION['user_id'], $router_id, $newPassword);
$stmt->execute();

$request_id = $stmt->insert_id;

createLog(
    $conn,
    'account',
    'Router password change requested',
    'Request ID: ' . $request_id,
    'info',
    $_SESSION['user_id']
);

/* 3️⃣ GET USER DETAILS (FOR SMS) */
$stmt = $conn->prepare("SELECT first_name, phone_number FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* 4️⃣ SEND TO VPS (NON-BLOCKING SMS TRIGGER) */
$vpsPayload = json_encode([
     'secret'       => 'MY_SECRET_123',
    'client_name'  => $user['first_name'],
    'client_phone' => $user['phone_number'],
    'request_id'   => $request_id
]);

$ch = curl_init("http://162.245.191.109/admin_password_request_sms.php");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $vpsPayload,
    CURLOPT_TIMEOUT        => 3   // VERY IMPORTANT: do not hang site
]);

$response = curl_exec($ch);

if(curl_errno($ch)){

    createLog(
        $conn,
        'integration',
        'Password request SMS failed',
        curl_error($ch),
        'error',
        $_SESSION['user_id']
    );

} else {

    createLog(
        $conn,
        'integration',
        'Password request SMS sent',
        'Request ID: ' . $request_id,
        'success',
        $_SESSION['user_id']
    );

}
curl_close($ch);

/* 5️⃣ DONE */
echo json_encode(['success' => true]);
