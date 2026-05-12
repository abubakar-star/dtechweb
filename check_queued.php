<?php
$conn = new mysqli(
    "localhost",
    "root",
    "",
    "dlink_network"
);

$res = $conn->query("
    SELECT id, username 
    FROM users 
    WHERE status = 'queued'
    ORDER BY id DESC
    LIMIT 1
");

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode([
        "latest_id" => (int)$row['id'],
        "username"  => $row['username']
    ]);
} else {
    echo json_encode([
        "latest_id" => 0
    ]);
}
