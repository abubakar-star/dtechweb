<?php

// ===============================
// NO LOGIN REQUIRED
// ===============================
date_default_timezone_set("Africa/Nairobi");

// ===============================
// DB CONNECTION
// ===============================
$conn = new mysqli("sql313.infinityfree.com", "if0_39741603", "mkala3771", "if0_39741603_dlink_network");

if ($conn->connect_error) {
    die("Database connection failed");
}

// ===============================
// AUTO EXPIRE USERS AFTER 30 DAYS
// ===============================
$expireSql = "
    UPDATE users
    SET status = 'inactive'
    WHERE status = 'active'
    AND DATE_ADD(created_at, INTERVAL 30 DAY) < NOW()
";

$conn->query($expireSql);


// ===============================
// HANDLE ACTIVATE ACTION
// ===============================
$message = "";
if (isset($_POST['activate_user'])) {
    $userId = (int)$_POST['user_id'];

    // Fetch user details
    $res = $conn->query("SELECT username, password, phone_number, created_at, status FROM users WHERE id = $userId LIMIT 1");

    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();

        if ($user['status'] === 'queued') {
            // Activate user
            $update = $conn->query("UPDATE users SET status = 'active' WHERE id = $userId");

            if ($update) {

            require 'notifyActivate_vps.php';

notifyVPSActivate([
    "phone"    => $user['phone_number'],
    "username" => $user['username']
]);

                // ===============================
                // SEND SMS TO CLIENT VIA TALKSASA v3 API
                // ===============================
                $apiToken = "1542|hXcLrZrnhKrCfiRS8rYsUPWpLRASQMb81JPjAgRC3b1a4eaf"; // Replace with your v3 token
                $recipient = $user['phone_number']; // must be in international format
                $senderId = "D-LINK"; // max 11 characters
                $smsMessage = "Hello " . $user['username'] . ",\nYour internet subscription is now ACTIVE.\nYou can now connect.";

                $payload = json_encode([
                    "recipient" => $recipient,
                    "sender_id" => $senderId,
                    "type" => "plain",
                    "message" => $smsMessage
                ]);

                $ch = curl_init("https://bulksms.talksasa.com/api/v3/sms/send");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer $apiToken",
                    "Content-Type: application/json",
                    "Accept: application/json"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

              $response = curl_exec($ch);

if(curl_errno($ch)){
    echo "CURL Error: " . curl_error($ch);
} else {
    echo "Response: " . ($response ?: 'No response, likely blocked');
}
                curl_close($ch);

                $message = "✅ User ID $userId activated successfully! " . $message;
            } else {
                $message = "❌ Failed to activate user ID $userId: " . $conn->error;
            }
        } else {
            $message = "⚠️ User ID $userId is already active or expired.";
        }
    } else {
        $message = "❌ User not found.";
    }
}

// ===============================
// FETCH USERS
// ===============================
$result = $conn->query("
    SELECT 
        id,
        username,
        password,
        phone_number,
        created_at,
        status
    FROM users
    ORDER BY 
        CASE status
            WHEN 'queued' THEN 1
            WHEN 'inactive' THEN 2
            WHEN 'active' THEN 3
            ELSE 4
        END,
        created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Status | D-LINK Network</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

<div class="max-w-6xl mx-auto bg-white shadow rounded-lg p-6">
    <h1 class="text-2xl font-bold mb-4 text-gray-800">📋 All Users Status</h1>

    <?php if ($message): ?>
        <div class="mb-4 p-3 bg-blue-100 text-blue-800 rounded"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-200 text-left text-sm uppercase text-gray-600">
                    <th class="p-3">#</th>
                    <th class="p-3">Username</th>
                    <th class="p-3">Password</th>
                    <th class="p-3">Date</th>
                    <th class="p-3">Status</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3"><?= $row['id'] ?></td>
                            <td class="p-3 font-medium"><?= htmlspecialchars($row['username']) ?></td>
                            <td class="p-3 font-medium"><?= htmlspecialchars($row['password']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($row['created_at']) ?></td>
                            <td class="p-3">
                                <?php
                                    if ($row['status'] === 'active') {
                                        echo '<span class="px-2 py-1 text-xs rounded bg-green-100 text-green-700">Active</span>';
                                    } elseif ($row['status'] === 'queued') {
                                        echo '<span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-700">Queued</span>';
                                    } else {
                                        echo '<span class="px-2 py-1 text-xs rounded bg-red-100 text-red-700">Expired</span>';
                                    }
                                ?>
                            </td>
                            <td class="p-3">
                                <?php if ($row['status'] === 'queued'): ?>
                                    <form method="post">
                                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="activate_user"
                                                class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                                            Activate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-4 text-center text-gray-500">No users found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let lastQueuedId = localStorage.getItem("lastQueuedId") || 0;

// Request permission once
if ("Notification" in window && Notification.permission !== "granted") {
    Notification.requestPermission();
}

// Sound
const alertSound = new Audio("https://actions.google.com/sounds/v1/alarms/beep_short.ogg");

function checkNewQueuedUser() {
    fetch("check_queued.php")
        .then(res => res.json())
        .then(data => {
            if (data.latest_id > lastQueuedId) {
                showQueuedNotification(data.username);
                lastQueuedId = data.latest_id;
                localStorage.setItem("lastQueuedId", lastQueuedId);
            }
        });
}

function showQueuedNotification(username) {
    if (Notification.permission === "granted") {

        // 🔔 Sound
        alertSound.play();

        // 📳 Vibration
        if (navigator.vibrate) {
            navigator.vibrate([300, 200, 300]);
        }

        const notification = new Notification("🚨 USER QUEUED", {
            body: username + " is waiting for activation",
            icon: "https://cdn-icons-png.flaticon.com/512/1828/1828843.png",
            badge: "https://cdn-icons-png.flaticon.com/512/1828/1828843.png",
            requireInteraction: true
        });

        // 👉 Open admin page on tap
        notification.onclick = function () {
            window.focus();
            window.location.href = "user_status.php"; // change if needed
        };
    }
}

// Check every 10 seconds
setInterval(checkNewQueuedUser, 10000);
checkNewQueuedUser();
</script>


</body>
</html>
