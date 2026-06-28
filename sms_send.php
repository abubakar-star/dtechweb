<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set("Africa/Nairobi");

/* ===============================
   DB CONNECTION
================================ */
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed");
}

/* ===============================
   TEMP ADMIN ID
================================ */
$admin_id = 1; // Temporary until admin authentication exists

$success = '';
$error = '';
$api_response = '';

/* ===============================
   LOAD ACTIVE SMS SETTINGS
================================ */
$stmt = $conn->prepare("
    SELECT api_token, sender_id
    FROM sms_settings
    WHERE is_active = 1
    LIMIT 1
");

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $error = "SMS module is disabled or not configured.";
} else {
    $settings = $result->fetch_assoc();
}

/* ===============================
   SEND SMS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $phone = trim($_POST['phone']);
    $message = trim($_POST['message']);

    if (empty($phone) || empty($message)) {

        $error = "All fields are required.";

    } else {

        /* Format phone number */
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }

        $data = [
            "recipient" => $phone,
            "sender_id" => $settings['sender_id'],
            "message" => $message
        ];

        /* SEND TO TALKSASA */
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            "https://bulksms.talksasa.com/api/v3/sms/send"
        );

        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($data)
        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $settings['api_token'],
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);

        curl_close($ch);

        $api_response = $response;

        $status = $curl_error ? 'Failed' : 'Sent';

        if ($curl_error) {
            $error = "SMS failed: " . $curl_error;
        } else {
            $success = "SMS sent successfully.";
        }

        /* SAVE TO HISTORY */
        $campaign_id = null;
        $user_id = null;
        $recipient_name = 'Manual Test';
        $sms_type = 'Test SMS';
        $cost = 0;

        $stmt = $conn->prepare("
            INSERT INTO sms_history
            (
                campaign_id,
                user_id,
                phone,
                recipient_name,
                message,
                sms_type,
                provider_response,
                status,
                cost,
                sent_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iissssssdi",
            $campaign_id,
            $user_id,
            $phone,
            $recipient_name,
            $message,
            $sms_type,
            $api_response,
            $status,
            $cost,
            $admin_id
        );

        $stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Send Test SMS</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-4xl mx-auto p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">

        <h1 class="text-3xl font-bold text-white">
            ✉ Send Test SMS
        </h1>

        <a href="sms.php"
           class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
            ← Back
        </a>

    </div>

    <!-- Messages -->
    <?php if ($success): ?>
        <div class="bg-green-600 text-white p-4 rounded-lg mb-6">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-600 text-white p-4 rounded-lg mb-6">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="bg-gray-800 rounded-xl p-8 shadow">

        <h2 class="text-2xl font-bold text-white mb-6">
            Send SMS
        </h2>

        <form method="POST" class="space-y-6">

            <div>
                <label class="block text-gray-300 mb-2">
                    Phone Number
                </label>

                <input type="text"
                       name="phone"
                       placeholder="2547XXXXXXXX"
                       required
                       class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>

            <div>
                <label class="block text-gray-300 mb-2">
                    Message
                </label>

                <textarea
                    name="message"
                    rows="6"
                    maxlength="160"
                    required
                    class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Type your SMS here..."></textarea>
            </div>

            <button type="submit"
                    class="w-full bg-purple-700 hover:bg-purple-800 text-white font-bold py-3 rounded-lg transition">
                Send SMS
            </button>

        </form>

    </div>

    <!-- API Response -->
    <?php if (!empty($api_response)): ?>

        <div class="bg-gray-800 rounded-xl p-8 shadow mt-6">

            <h2 class="text-xl font-bold text-white mb-4">
                TalkSasa Response
            </h2>

            <pre class="bg-gray-900 text-green-400 p-4 rounded overflow-x-auto text-sm">
<?= htmlspecialchars($api_response) ?>
            </pre>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
