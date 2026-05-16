<?php
header('Content-Type: application/json');

// Database connection
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['issueTitle'] ?? '');
    $message = trim($_POST['issueMessage'] ?? '');
    $user_id = 1; // Replace with $_SESSION['user_id']

    if ($title && $message) {
        $stmt = $conn->prepare("INSERT INTO support (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $user_id, $title, $message);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Your report has been submitted successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to submit report."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Please fill in all fields."]);
    }
}
$conn->close();
