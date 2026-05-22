<?php
session_start();

require_once 'includes/logger.php';

header('Content-Type: application/json');

// DB connection

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {

   error_log(
    'Support API database connection failed: ' . $conn->connect_error
);

    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);

    exit();
}

// Require login
if (!isset($_SESSION['user_id'])) {

    createLog(
        $conn,
        'security',
        'Unauthorized support API access',
        'Someone tried to access support_api.php without logging in',
        'warning',
        null
    );

    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}




$user_id = (int) $_SESSION['user_id'];
$action  = isset($_GET['action']) ? $_GET['action'] : '';

// ✅ GET: fetch tickets (with ?limit=5|10|all)
if ($action === 'get_tickets') {
    $limitParam = isset($_GET['limit']) ? strtolower(trim($_GET['limit'])) : '5';

    $sql = "SELECT id, title, message, status, priority, created_at 
            FROM support 
            WHERE user_id = ? 
            ORDER BY created_at DESC";

    if ($limitParam === 'all') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
    } else {
        $limit = in_array($limitParam, ['5', '10'], true) ? (int)$limitParam : 5;
        $sql  .= " LIMIT ?";
        $stmt  = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $limit);
    }

  if (!$stmt || !$stmt->execute()) {

    createLog(
        $conn,
        'support',
        'Failed to fetch support tickets',
        'Database error while fetching tickets for user ID: ' . $user_id,
        'error',
        $user_id
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch tickets'
    ]);

    exit();
}

    $result  = $stmt->get_result();
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }

    echo json_encode(['status' => 'success', 'tickets' => $tickets]);
    exit();
}

// ✅ POST: create ticket
// ✅ POST: create ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {

        createLog(
            $conn,
            'security',
            'Invalid CSRF token',
            'User submitted invalid CSRF token in support ticket form',
            'warning',
            $user_id
        );

        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid CSRF token'
        ]);

        exit();
    }

    $title   = isset($_POST['issueTitle']) ? trim($_POST['issueTitle']) : '';
    $message = isset($_POST['issueMessage']) ? trim($_POST['issueMessage']) : '';

if ($title === '' || $message === '') {

    createLog(
        $conn,
        'support',
        'Empty support ticket submission',
        'User attempted to submit empty support form',
        'warning',
        $user_id
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'All fields are required'
    ]);

    exit();
}

    // ✅ Default values for new ticket
    $status   = 'pending';  // default status is pending
    $priority = 'normal';   // optional priority

    $stmt = $conn->prepare("INSERT INTO support (user_id, title, message, status, priority, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
   if (!$stmt) {

    createLog(
        $conn,
        'database',
        'Support ticket prepare failed',
        $conn->error,
        'error',
        $user_id
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Prepare failed'
    ]);

    exit();
}
    $stmt->bind_param("issss", $user_id, $title, $message, $status, $priority);

    if ($stmt->execute()) {

    createLog(
        $conn,
        'support',
        'Support ticket submitted',
        'User submitted a new support ticket: ' . $title,
        'info',
        $user_id
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Ticket submitted successfully'
    ]);
} else {

    createLog(
        $conn,
        'database',
        'Support ticket save failed',
        $stmt->error,
        'error',
        $user_id
    );

    echo json_encode([
        'status' => 'error',
        'message' => 'Database error while saving ticket'
    ]);
}
    exit();
}

// ✅ Fallback for invalid action
createLog(
    $conn,
    'security',
    'Invalid support API action',
    'Invalid action attempted in support_api.php',
    'warning',
    $user_id
);

echo json_encode([
    'status' => 'error',
    'message' => 'Invalid action'
]);
