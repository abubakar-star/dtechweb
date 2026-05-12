<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$conn = new mysqli("sql313.infinityfree.com", "if0_39741603", "mkala3771", "if0_39741603_dlink_network");
if ($conn->connect_error) {
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
            $updateStmt->execute();
        }
    }
}

echo json_encode($logs);
