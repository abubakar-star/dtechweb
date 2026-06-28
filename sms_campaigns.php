<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli(
    $host,
    $username,
    $password,
    $dbname,
    $port
);

if ($conn->connect_error) {
    die("Database connection failed");
}

$campaigns = [];

$result = $conn->query("
    SELECT *
    FROM sms_campaigns
    ORDER BY created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $campaigns[] = $row;
}

$totalCampaigns = count($campaigns);

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>SMS Campaign History</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-7xl mx-auto p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">

        <h1 class="text-3xl font-bold text-white">
            📊 SMS Campaign History
        </h1>

        <a href="sms.php"
           class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">

            ← Back

        </a>

    </div>


    <!-- Statistics Card -->
    <div class="bg-gray-800 rounded-xl p-6 shadow mb-8">

        <h2 class="text-xl font-bold text-white mb-2">

            Total Campaigns

        </h2>

        <p class="text-4xl font-bold text-green-400">

            <?= $totalCampaigns ?>

        </p>

    </div>


    <!-- Campaign Table -->
    <div class="bg-gray-800 rounded-xl p-8 shadow">

        <h2 class="text-2xl font-bold text-white mb-6">

            Campaign History

        </h2>

        <?php if(empty($campaigns)): ?>

            <p class="text-gray-400">

                No campaigns found.

            </p>

        <?php else: ?>

        <div class="overflow-x-auto">

            <table class="w-full text-left">

                <thead>

                <tr class="border-b border-gray-700">

                    <th class="py-3 text-gray-300">
                        Title
                    </th>

                    <th class="py-3 text-gray-300">
                        Type
                    </th>

                    <th class="py-3 text-gray-300">
                        Group
                    </th>

                    <th class="py-3 text-gray-300">
                        Recipients
                    </th>

                    <th class="py-3 text-gray-300">
                        Successful
                    </th>

                    <th class="py-3 text-gray-300">
                        Failed
                    </th>

                    <th class="py-3 text-gray-300">
                        Status
                    </th>

                    <th class="py-3 text-gray-300">
                        Date
                    </th>

                </tr>

                </thead>

                <tbody>

                <?php foreach($campaigns as $campaign): ?>

                <tr class="border-b border-gray-700">

                    <td class="py-4 text-white">
                        <?= htmlspecialchars($campaign['title']) ?>
                    </td>

                    <td class="py-4 text-gray-300">
                        <?= htmlspecialchars($campaign['sms_type']) ?>
                    </td>

                    <td class="py-4 text-gray-300">
                        <?= htmlspecialchars($campaign['recipient_group']) ?>
                    </td>

                    <td class="py-4 text-gray-300">
                        <?= htmlspecialchars($campaign['total_recipients']) ?>
                    </td>

                    <td class="py-4 text-green-400">
                        <?= htmlspecialchars($campaign['successful']) ?>
                    </td>

                    <td class="py-4 text-red-400">
                        <?= htmlspecialchars($campaign['failed']) ?>
                    </td>

                    <td class="py-4 text-yellow-400">
                        <?= htmlspecialchars($campaign['status']) ?>
                    </td>

                    <td class="py-4 text-gray-400">
                        <?= htmlspecialchars($campaign['created_at']) ?>
                    </td>

                </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>