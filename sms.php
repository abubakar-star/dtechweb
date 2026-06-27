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

<div class="bg-gray-800 rounded-xl p-10 shadow">

<h2 class="text-xl font-semibold text-white mb-4">
Welcome
</h2>

<p class="text-gray-300">
The SMS Management module is ready.

In the next step we'll build the dashboard for sending messages, viewing campaigns, templates, history and settings.
</p>

</div>

</div>

</body>
</html>