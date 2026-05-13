<?php
session_start();

header('Content-Type: application/json');

include 'onasis_config.php';

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

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

$invDteInv = $_POST['invoice_number'] ?? '';

$sql = "SELECT 
            users.id,
            users.phone_number,
            users.username,
            users.account_number,
            packages.price
        FROM users
        LEFT JOIN packages 
        ON users.package_id = packages.id
        WHERE users.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
    exit;
}

$user = $result->fetch_assoc();

$phone = $user['phone_number'];
$amount = $user['price'];
$accountNumber = $user['account_number'];

$reference = "DLINK-" . $user['id'] . "-" . time();

$insert = $conn->prepare("
    INSERT INTO payments 
    (user_id, reference, amount, invoice_number, status)
    VALUES (?, ?, ?, ?, 'pending')
");

$insert->bind_param(
    "isds",
    $user_id,
    $reference,
    $amount,
    $invDteInv
);
$insert->execute();

$data = [
    "phone" => $phone,
    "amount" => $amount,

    // Internal transaction reference
    "reference" => $reference,

    // Customer account reference
    "account_ref" => $accountNumber,

    "callback_url" =>
    "https://4fcc-2c0f-fe38-232e-f837-6119-5789-1fd4-a9b7.ngrok-free.app/polling/FAIBA/onasis_callback.php"
];

$headers = [
    "x-api-key: " . $ONASIS_SECRET_KEY,
    "Content-Type: application/json",
    "Accept: application/json"
];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $ONASIS_BASE_URL . "/api/stk");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
file_put_contents("init_response.txt", $response);

if (curl_errno($ch)) {
    
    echo json_encode([
        "success" => false,
        "message" => curl_error($ch)
    ]);

} else {

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $result = json_decode($response, true);

    if (
        $httpCode == 200 &&
        isset($result['status']) &&
        (
            $result['status'] == 'pending' ||
            $result['status'] == 'success'
        )
    ) {

       echo json_encode([
    "success" => true,
    "reference" => $reference,
    "message" => "Waiting for payment confirmation"
]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => "Payment initialization failed",
            "response" => $result
        ]);
    }
}

curl_close($ch);
?>
