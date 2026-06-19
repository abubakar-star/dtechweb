<?php
session_start();

header('Content-Type: application/json');

include 'onasis_config.php';
include 'includes/logger.php';

/* =========================
   DB CONNECTION
========================= */
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

/* =========================
   SESSION CHECK
========================= */
if (!isset($_SESSION['user_id'])) {

createLog(
    $conn,
    'security',
    'Unauthorized payment initialization',
    'Attempt to initialize STK without login session',
    'warning'
);

    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

$package_id = $_POST['package_id'] ?? '';

if (empty($package_id)) {

    echo json_encode([
        "success" => false,
        "message" => "Package not selected"
    ]);
    exit;
}

/* =========================
   GET USER DETAILS
========================= */

$sql = "
SELECT 
    id,
    phone_number,
    username,
    account_number
FROM users
WHERE id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    createLog(
        $conn,
        'payment',
        'User lookup failed',
        'No user found during STK initialization',
        'error',
        $user_id
    );

    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);

    exit;
}

$user = $result->fetch_assoc();

/* =========================
   GET SELECTED PACKAGE
========================= */

$pkg = $conn->prepare("
SELECT id, package_name, price
FROM packages
WHERE id = ?
LIMIT 1
");

$pkg->bind_param("i", $package_id);
$pkg->execute();

$pkgResult = $pkg->get_result();

if ($pkgResult->num_rows == 0) {

    echo json_encode([
        "success" => false,
        "message" => "Selected package not found"
    ]);

    exit;
}

$package = $pkgResult->fetch_assoc();

/* =========================
   ASSIGN VARIABLES
========================= */

$phone = $user['phone_number'];
$amount = $package['price'];
$accountNumber = $user['account_number'];

$invDteInv = $_POST['invoice_number'] ?? '';

/* =========================
   GET USER DETAILS
========================= */


$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

createLog(
    $conn,
    'payment',
    'User lookup failed',
    'No user found during STK initialization',
    'error',
    $user_id
);

    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

$user = $result->fetch_assoc();

$phone = $user['phone_number'];
$accountNumber = $user['account_number'];

/* =========================
   CREATE REFERENCE
========================= */
$reference = "DLINK-" . $user['id'] . "-" . time();

/* =========================
   SAVE PAYMENT (PENDING)
========================= */
$insert = $conn->prepare("
INSERT INTO payments
(
    user_id,
    package_id,
    reference,
    amount,
    payment_type,
    invoice_number,
    status
)
VALUES
(
    ?, ?, ?, ?, 'subscription', ?, 'pending'
)
");

$insert->bind_param(
    "iisds",
    $user_id,
    $package_id,
    $reference,
    $amount,
    $invDteInv
);

$insert->execute();

createLog(
    $conn,
    'payment',
    'Pending payment created',
    'Reference: ' . $reference .
    ' | Amount: ' . $amount,
    'info',
    $user_id
);

/* =========================
   PREPARE STK REQUEST
========================= */
$data = [
    "phone" => $phone,
    "amount" => $amount,
    "reference" => $reference,
    "account_ref" => $accountNumber,
    "callback_url" => "https://dtechweb.onrender.com/onasis_callback.php"
];

$headers = [
    "x-api-key: " . $ONASIS_SECRET_KEY,
    "Content-Type: application/json",
    "Accept: application/json"
];

/* =========================
   CURL REQUEST
========================= */
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $ONASIS_BASE_URL . "/api/stk");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

/* =========================
   CURL ERROR CHECK
========================= */
if ($response === false) {

createLog(
    $conn,
    'payment',
    'STK API connection failed',
    curl_error($ch),
    'error',
    $user_id
);

    echo json_encode([
        "success" => false,
        "curl_error" => curl_error($ch),
        "curl_errno" => curl_errno($ch)
    ]);
    curl_close($ch);
    exit;
}

/* =========================
   RESPONSE HANDLING
========================= */
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

/* =========================
   LOGGING (VERY IMPORTANT)
========================= */
file_put_contents(
    "stk_log.txt",
    date('c') . " " . $response . PHP_EOL,
    FILE_APPEND
);

/* =========================
   FINAL RESPONSE
========================= */
if ($httpCode == 200 && isset($result['status'])) {

createLog(
    $conn,
    'payment',
    'STK push sent',
    'Reference: ' . $reference,
    'success',
    $user_id
);

    echo json_encode([
        "success" => true,
        "http_code" => $httpCode,
        "transaction_id" => $result['transaction_id'] ?? null,
        "reference" => $reference,
        "message" => "STK push sent — waiting for payment"
    ]);

} else {

createLog(
    $conn,
    'payment',
    'Payment Server Unreachable',
    json_encode($result),
    'error',
    $user_id
);

    echo json_encode([
        "success" => false,
        "http_code" => $httpCode,
        "message" => "Payment Server Unreachable",
        "response" => $result
    ]);
}

exit;
?>
