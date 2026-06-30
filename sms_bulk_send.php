<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sms_bulk.php");
    exit();
}

/* ===============================
   DATABASE CONNECTION
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
   GET FORM DATA
================================ */

$campaignTitle = trim($_POST['campaign_title']);
$recipientGroup = trim($_POST['recipient_group']);
$packageId = (int)($_POST['package_id'] ?? 0);
$message = trim($_POST['message']);

?>

<!DOCTYPE html>
<html>

<head>

<title>Bulk SMS Send</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 text-white">

<div class="max-w-3xl mx-auto mt-10 bg-gray-800 p-8 rounded-lg">

<h2 class="text-2xl font-bold mb-6">
Bulk SMS Send
</h2>

<p><strong>Campaign:</strong> <?= htmlspecialchars($campaignTitle) ?></p>

<p><strong>Recipient Group:</strong> <?= htmlspecialchars($recipientGroup) ?></p>

<p><strong>Package ID:</strong> <?= $packageId ?></p>

<div class="mt-5">

<strong>Message</strong>

<div class="bg-gray-700 rounded p-4 mt-2 whitespace-pre-wrap">

<?= htmlspecialchars($message) ?>

</div>

</div>

</div>

</body>

</html>