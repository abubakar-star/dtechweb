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
   SMS DASHBOARD STATISTICS
================================ */

// Total Campaigns
$total_campaigns = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM sms_campaigns");
if ($res) {
    $total_campaigns = (int)$res->fetch_assoc()['total'];
}

// Total SMS Sent
$total_sms = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM sms_history");
if ($res) {
    $total_sms = (int)$res->fetch_assoc()['total'];
}

// Pending Queue
$pending_sms = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM sms_queue WHERE processed = 0");
if ($res) {
    $pending_sms = (int)$res->fetch_assoc()['total'];
}

// Failed SMS
$failed_sms = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM sms_history WHERE status='Failed'");
if ($res) {
    $failed_sms = (int)$res->fetch_assoc()['total'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SMS Management</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-7xl mx-auto p-8">

<div class="flex items-center justify-between mb-8">

<h1 class="text-3xl font-bold text-white">
📨 SMS Management
</h1>

<a href="tosha.php"
   class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
← Back
</a>

</div>

<!-- Statistics -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

    <div class="bg-gray-800 rounded-xl p-6 shadow">
        <p class="text-gray-400 text-sm">Campaigns</p>
        <h2 class="text-3xl font-bold text-white mt-2">
            <?= $total_campaigns ?>
        </h2>
    </div>

    <div class="bg-gray-800 rounded-xl p-6 shadow">
        <p class="text-gray-400 text-sm">SMS Sent</p>
        <h2 class="text-3xl font-bold text-green-400 mt-2">
            <?= $total_sms ?>
        </h2>
    </div>

    <div class="bg-gray-800 rounded-xl p-6 shadow">
        <p class="text-gray-400 text-sm">Pending Queue</p>
        <h2 class="text-3xl font-bold text-yellow-400 mt-2">
            <?= $pending_sms ?>
        </h2>
    </div>

    <div class="bg-gray-800 rounded-xl p-6 shadow">
        <p class="text-gray-400 text-sm">Failed SMS</p>
        <h2 class="text-3xl font-bold text-red-400 mt-2">
            <?= $failed_sms ?>
        </h2>
    </div>

</div>

<!-- Action Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <a href="sms_send.php"
       class="bg-purple-700 hover:bg-purple-800 transition rounded-xl p-8">

        <h2 class="text-2xl font-bold text-white mb-2">
            ✉ Send SMS
        </h2>

        <p class="text-gray-200">
            Create a new SMS campaign.
        </p>

    </a>

    <a href="sms_templates.php"
       class="bg-blue-700 hover:bg-blue-800 transition rounded-xl p-8">

        <h2 class="text-2xl font-bold text-white mb-2">
            📝 Templates
        </h2>

        <p class="text-gray-200">
            Manage reusable SMS templates.
        </p>

    </a>

    <a href="sms_campaigns.php"
       class="bg-green-700 hover:bg-green-800 transition rounded-xl p-8">

        <h2 class="text-2xl font-bold text-white mb-2">
            📊 Campaign History
        </h2>

        <p class="text-gray-200">
            View all SMS campaigns.
        </p>

    </a>

    <a href="sms_settings.php"
       class="bg-orange-700 hover:bg-orange-800 transition rounded-xl p-8">

        <h2 class="text-2xl font-bold text-white mb-2">
            ⚙ SMS Settings
        </h2>

        <p class="text-gray-200">
            Configure TalkSasa API.
        </p>

    </a>

</div>

</div>

</body>
</html>
