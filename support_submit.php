<?php
header('Content-Type: application/json');

require_once 'includes/logger.php';

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

    createLog(
        $conn,
        'support',
        'Support ticket submitted',
        'User submitted support request: ' . $title,
        'info',
        $user_id
    );

    echo json_encode([
        "status" => "success",
        "message" => "Your report has been submitted successfully!"
    ]);

} else {

    createLog(
        $conn,
        'support',
        'Support ticket submission failed',
        'Database failed to save support request: ' . $conn->error,
        'error',
        $user_id
    );

    echo json_encode([
        "status" => "error",
        "message" => "Failed to submit report."
    ]);
}
        $stmt->close();
   } else {

    createLog(
        $conn,
        'support',
        'Incomplete support submission',
        'User attempted to submit support form with missing fields',
        'warning',
        $user_id
    );

    echo json_encode([
        "status" => "error",
        "message" => "Please fill in all fields."
    ]);
}
}
$conn->close();
