<?php
session_start();

include 'includes/logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {

    createLog(
        $conn ?? null,
        null,
        null,
        'security',
        'unauthorized_password_log_access',
        'Unauthorized access attempt to fetch_password_logs.php',
        'warning'
    );

    echo json_encode([]);
    exit;
}
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {

    error_log(
        "Database connection failed in fetch_password_logs.php"
    );

    echo json_encode([]);
    exit;
}

$sql = "
 SELECT created_at, status
 FROM router_password_requests
 WHERE user_id = ?
 ORDER BY created_at DESC
 LIMIT 100
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();

$logs = [];
while ($row = $res->fetch_assoc()) {
    $logs[] = [
        'date'   => date('M d, Y H:i:s', strtotime($row['created_at'])),
        'event'  => 'Router Password Change',
        'status' => ucfirst($row['status'])
    ];

    
    // ✅ Update user's router password if status = changed
    if (strtolower($row['status']) === 'changed') {
        // Get the latest password request value
        $passSql = "SELECT new_password FROM router_password_requests WHERE user_id = ? AND status = 'changed' ORDER BY created_at DESC LIMIT 1";
        $passStmt = $conn->prepare($passSql);
        $passStmt->bind_param("i", $_SESSION['user_id']);
        $passStmt->execute();
        $passResult = $passStmt->get_result();
        $passRow = $passResult->fetch_assoc();

        if ($passRow && !empty($passRow['new_password'])) {
            $updateSql = "UPDATE users SET router_password = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $passRow['new_password'], $_SESSION['user_id']);
            if($updateStmt->execute()){

    createLog(
        $conn,
        $_SESSION['user_id'],
        null,
        'router',
        'router_password_synced',
        'Router password synced successfully',
        'success'
    );
}
        }
    }
}

echo json_encode($logs);
