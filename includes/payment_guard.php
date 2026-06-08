<?php

$stmt = $conn->prepare("
    SELECT dashboard_override
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();

$dashboardOverride = $userData['dashboard_override'] ?? 'off';

$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM payments
    WHERE user_id = ?
    AND status = 'completed'
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($paymentCount);
$stmt->fetch();
$stmt->close();

if ($dashboardOverride === 'on' && $paymentCount == 0) {
    header("Location: index.php");
    exit();
}