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
$admin_id = 1; // Temporary until admin authentication is implemented

/* ===============================
   DEFAULT VALUES
================================ */
$provider_name = 'TalkSasa';
$api_token = '';
$sender_id = '';
$is_active = 1;

$success = '';
$error = '';

/* ===============================
   LOAD EXISTING SETTINGS
================================ */
$stmt = $conn->prepare("SELECT * FROM sms_settings LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $settings = $result->fetch_assoc();

    $provider_name = $settings['provider_name'];
    $api_token     = $settings['api_token'];
    $sender_id     = $settings['sender_id'];
    $is_active     = $settings['is_active'];
}

/* ===============================
   SAVE SETTINGS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $provider_name = 'TalkSasa';
    $api_token = trim($_POST['api_token']);
    $sender_id = trim($_POST['sender_id']);
    $is_active = (int)$_POST['is_active'];

    if (empty($api_token) || empty($sender_id)) {

        $error = "API Token and Sender ID are required.";

    } else {

        $check = $conn->query("SELECT id FROM sms_settings LIMIT 1");

        if ($check->num_rows == 0) {

            // INSERT
            $stmt = $conn->prepare("
                INSERT INTO sms_settings
                (provider_name, api_token, sender_id, is_active, updated_by)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssii",
                $provider_name,
                $api_token,
                $sender_id,
                $is_active,
                $admin_id
            );

        } else {

            // UPDATE
            $row = $check->fetch_assoc();
            $id = $row['id'];

            $stmt = $conn->prepare("
                UPDATE sms_settings
                SET
                    api_token = ?,
                    sender_id = ?,
                    is_active = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssiii",
                $api_token,
                $sender_id,
                $is_active,
                $admin_id,
                $id
            );
        }

        if ($stmt->execute()) {
            $success = "SMS settings saved successfully.";
        } else {
            $error = "Failed to save settings.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SMS Settings</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-4xl mx-auto p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">

        <h1 class="text-3xl font-bold text-white">
            ⚙ SMS Settings
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

    <!-- Settings Card -->
    <div class="bg-gray-800 rounded-xl p-8 shadow">

        <h2 class="text-2xl font-bold text-white mb-6">
            TalkSasa Configuration
        </h2>

        <form method="POST" class="space-y-6">

            <!-- Provider -->
            <div>
                <label class="block text-gray-300 mb-2">
                    Provider
                </label>

                <input type="text"
                       value="<?= htmlspecialchars($provider_name) ?>"
                       readonly
                       class="w-full bg-gray-700 text-gray-300 rounded-lg p-3 border border-gray-600">
            </div>

            <!-- API Token -->
            <div>
                <label class="block text-gray-300 mb-2">
                    API Token
                </label>

                <input type="password"
                       name="api_token"
                       value="<?= htmlspecialchars($api_token) ?>"
                       required
                       class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <!-- Sender ID -->
            <div>
                <label class="block text-gray-300 mb-2">
                    Sender ID
                </label>

                <input type="text"
                       name="sender_id"
                       value="<?= htmlspecialchars($sender_id) ?>"
                       required
                       class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600 focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <!-- Status -->
            <div>
                <label class="block text-gray-300 mb-2">
                    SMS Status
                </label>

                <select name="is_active"
                        class="w-full bg-gray-700 text-white rounded-lg p-3 border border-gray-600">

                    <option value="1" <?= $is_active == 1 ? 'selected' : '' ?>>
                        Enabled
                    </option>

                    <option value="0" <?= $is_active == 0 ? 'selected' : '' ?>>
                        Disabled
                    </option>

                </select>
            </div>

            <!-- Submit -->
            <button type="submit"
                    class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-3 rounded-lg transition">
                Save Settings
            </button>

        </form>

    </div>

</div>

</body>
</html>