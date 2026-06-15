<?php

session_start();

if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['is_admin'])
) {
    header("Location: index.php");
    exit();
}
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
   NOTIFICATION COUNTS
================================ */

/* 🔔 Pending device bindings */
$pending_bindings = 0;
$res1 = $conn->query("
    SELECT COUNT(*) AS total 
    FROM bindings 
    WHERE status = 'pending'
");
if ($res1) {
    $pending_bindings = (int)$res1->fetch_assoc()['total'];
}

/* 🔔 Queued users (waiting activation) */
$queued_users = 0;
$res2 = $conn->query("
    SELECT COUNT(*) AS total 
    FROM users 
    WHERE status = 'queued'
");
if ($res2) {
    $queued_users = (int)$res2->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Panel | D-LINK Network</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen flex items-center justify-center text-white">

<div class="w-full max-w-md bg-gray-800 rounded-xl shadow-xl p-8">

<h1 class="text-2xl font-bold text-center mb-2">
🔐 D-LINK Admin Panel
</h1>

<p class="text-center text-gray-400 text-sm mb-6">
Choose an admin module
</p>

<div class="space-y-4">

<!-- USERS REGISTRATION -->
<a href="admin_users.php"
   class="relative block text-center
          bg-green-600 hover:bg-green-700
          transition py-4 rounded-lg font-semibold">

👥 Users Registration

</a>

<!-- PAYMENTS -->
<a href="payments_page.php"
   class="relative block text-center
          bg-emerald-600 hover:bg-emerald-700
          transition py-4 rounded-lg font-semibold">

💳 Payments

</a>

<!-- LOGS -->
<a href="admin_logs.php"
   class="relative block text-center
          bg-gray-600 hover:bg-gray-700
          transition py-4 rounded-lg font-semibold">

📜 Logs

</a>

<!-- DEVICE BINDINGS -->
<a href="admin_password_requests.php"
   class="relative block text-center
          bg-blue-600 hover:bg-blue-700
          transition py-4 rounded-lg font-semibold">

🔑 Password Requests

<?php if ($pending_bindings > 0): ?>
<span class="absolute -top-2 -right-2
             bg-blue-600 text-white text-xs font-bold
             px-2 py-1 rounded-full shadow">
<?= $pending_bindings ?>
</span>
<?php endif; ?>

</a>

<!-- USERS STATUS -->
<a href="users_status.php"
   class="relative block text-center
          bg-amber-600 hover:bg-amber-700
          transition py-4 rounded-lg font-semibold">

📊 Users Status

<?php if ($queued_users > 0): ?>
<span class="absolute -top-2 -right-2
             bg-red-600 text-white text-xs font-bold
             px-2 py-1 rounded-full shadow">
<?= $queued_users ?>
</span>
<?php endif; ?>

</a>

</div>

<hr class="my-6 border-gray-700">

<p class="text-xs text-center text-gray-500">
D-LINK Network • Admin Tools
</p>

</div>

</body>
</html>
