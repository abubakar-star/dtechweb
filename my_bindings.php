<?php

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

session_start();
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

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

/* ===============================
   FLASH MESSAGES
================================ */
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type    = $_SESSION['flash_type'] ?? '';
$highlight_row = $_SESSION['highlight_row'] ?? null;
unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['highlight_row']);

/* ===============================
   PRESERVE FORM VALUES
================================ */
$old_device = $_SESSION['old_device'] ?? '';
$old_ip     = $_SESSION['old_ip'] ?? '';
$old_mac    = $_SESSION['old_mac'] ?? '';
unset($_SESSION['old_device'], $_SESSION['old_ip'], $_SESSION['old_mac']);

/* ===============================
   HANDLE POST ACTIONS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // DELETE BINDING
    if (isset($_POST['delete_binding'])) {
        $binding_id = (int)$_POST['binding_id'];
        $stmt = $conn->prepare(
            "DELETE FROM bindings 
             WHERE id = ? AND user_id = ? AND status != 'approved'"
        );
        $stmt->bind_param("ii", $binding_id, $user_id);
        $stmt->execute();

        $_SESSION['flash_message'] = "✅ Device deleted successfully";
        $_SESSION['flash_type'] = "success";
        header("Location: my_bindings.php");
        exit();
    }

    // SUBMIT NEW BINDING
    if (isset($_POST['submit_binding'])) {

        $old_device = trim($_POST['device_name']);
        $old_ip     = trim($_POST['ip_address']);
        $old_mac    = trim($_POST['mac_address']);

        // Store old values in session
        $_SESSION['old_device'] = $old_device;
        $_SESSION['old_ip']     = $old_ip;
        $_SESSION['old_mac']    = $old_mac;

        // VALIDATION
        if ($old_device === '') {
            $_SESSION['flash_message'] = "❌ Device name is required";
            $_SESSION['flash_type'] = "error";
        } elseif (!filter_var($old_ip, FILTER_VALIDATE_IP)) {
            $_SESSION['flash_message'] = "❌ Invalid IP address";
            $_SESSION['flash_type'] = "error";
        } elseif (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $old_mac)) {
            $_SESSION['flash_message'] = "❌ Invalid MAC address format (AA:BB:CC:DD:EE:FF)";
            $_SESSION['flash_type'] = "error";
        } else {

            // Check duplicate IP+MAC (any status)
            $dup = $conn->prepare(
                "SELECT id FROM bindings 
                 WHERE user_id = ? AND ip_address = ? AND mac_address = ?"
            );
            $dup->bind_param("iss", $user_id, $old_ip, $old_mac);
            $dup->execute();
            $dup->store_result();
            $dup->bind_result($duplicate_id);
            $dup->fetch();

            if ($dup->num_rows > 0) {
                $_SESSION['flash_message'] = "❌ This IP & MAC already exists in your bindings";
                $_SESSION['flash_type'] = "error";

                // Highlight and temporarily move duplicate row
                $_SESSION['highlight_row'] = $duplicate_id;

                header("Location: my_bindings.php");
                exit();
            } else {

                // Check MAC approved for another user
                $check = $conn->prepare(
                    "SELECT id FROM bindings 
                     WHERE mac_address = ? AND status = 'approved'"
                );
                $check->bind_param("s", $old_mac);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $_SESSION['flash_message'] = "❌ This MAC is already approved for another user";
                    $_SESSION['flash_type'] = "error";
                } else {
                    // INSERT BINDING
                    $stmt = $conn->prepare(
                        "INSERT INTO bindings (user_id, device_name, ip_address, mac_address)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param("isss", $user_id, $old_device, $old_ip, $old_mac);
                    $stmt->execute();

                    $_SESSION['flash_message'] = "✅ Binding submitted and awaiting approval";
                    $_SESSION['flash_type'] = "success";

                    // Clear old values
                    unset($_SESSION['old_device'], $_SESSION['old_ip'], $_SESSION['old_mac']);
                }
            }
        }

        // Redirect to prevent form resubmission
        header("Location: my_bindings.php");
        exit();
    }
}

/* ===============================
   FETCH USER BINDINGS
================================ */
$stmt = $conn->prepare(
    "SELECT id, device_name, ip_address, mac_address, status, created_at
     FROM bindings
     WHERE user_id = ?
     ORDER BY (status='approved') DESC, created_at DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($id, $device_name, $ip_address, $mac_address, $status, $created_at);


/* ===============================
   ERROR FLAG FOR AUTO REFRESH
================================ */
$is_error = ($flash_message && $flash_type === 'error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Device Bindings | D-LINK Network</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-white min-h-screen p-6">

<div class="max-w-4xl mx-auto">

<!-- BACK BUTTON -->
<a href="index.php"
   class="inline-flex items-center gap-2 mb-4
          bg-gray-800 hover:bg-gray-700
          text-gray-200 px-4 py-2 rounded
          text-sm font-medium transition">

    ← Back to Dashboard
</a>

<h1 class="text-2xl font-bold mb-4">My Device Bindings</h1>


<!-- FLASH MESSAGE -->
<?php if ($flash_message): ?>
<div id="alertBox"
     class="mb-4 p-3 rounded
     <?= $flash_type === 'success' ? 'bg-green-600' : 'bg-red-600' ?>">
    <?= $flash_message ?>
</div>

<script>
// Auto-hide flash message
setTimeout(() => {
    const box = document.getElementById('alertBox');
    if (box) box.style.display = 'none';
}, 4000);
</script>
<?php endif; ?>

<!-- ADD DEVICE FORM -->
<div class="bg-gray-800 p-6 rounded-lg shadow mb-6">
<h2 class="font-semibold mb-4">Add New Device</h2>

<form method="POST" class="grid md:grid-cols-3 gap-4">

<input type="text" name="device_name"
       value="<?= htmlspecialchars($old_device) ?>"
       placeholder="Device name (Phone, Laptop)"
       class="bg-gray-900 border border-gray-600 rounded px-3 py-2" required>

<input type="text" name="ip_address"
       value="<?= htmlspecialchars($old_ip) ?>"
       placeholder="192.168.1.10"
       class="bg-gray-900 border border-gray-600 rounded px-3 py-2" required>

<input type="text" name="mac_address"
       value="<?= htmlspecialchars($old_mac) ?>"
       placeholder="AA:BB:CC:DD:EE:FF"
       class="bg-gray-900 border border-gray-600 rounded px-3 py-2" required>

<div class="md:col-span-3">
<button name="submit_binding"
        class="bg-blue-600 hover:bg-blue-700 px-5 py-2 rounded font-semibold">
Submit Binding
</button>
</div>

</form>
</div>

<!-- BINDINGS TABLE -->
<div class="bg-gray-800 rounded-lg overflow-x-auto">
<table class="min-w-full text-sm">
<thead class="bg-gray-700">
<tr>
    <th class="p-3 text-left">Device</th>
    <th class="p-3">IP</th>
    <th class="p-3">MAC</th>
    <th class="p-3">Status</th>
    <th class="p-3">Date</th>
    <th class="p-3 text-center">Action</th>
</tr>
</thead>
<tbody>

<?php
$i = 0;
if ($bindings->num_rows === 0): ?>
<tr>
<td colspan="6" class="p-4 text-center text-gray-400">
No devices submitted
</td>
</tr>
<?php endif; ?>

<?php while ($row = $bindings->fetch_assoc()): $i++; ?>
<tr id="binding-row-<?= $row['id'] ?>"
    data-original-index="<?= $i ?>"
    data-priority="<?= $row['status']=='approved' ? 1 : 2 ?>"
    class="border-b border-gray-700 hover:bg-gray-700/40">

<td class="p-3"><?= htmlspecialchars($row['device_name']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['ip_address']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['mac_address']) ?></td>

<td class="p-3">
<span class="px-2 py-1 rounded text-xs
<?= $row['status']=='approved' ? 'bg-green-600' :
   ($row['status']=='rejected' ? 'bg-red-600' : 'bg-yellow-500') ?>">
<?= ucfirst($row['status']) ?>
</span>
</td>

<td class="p-3 text-gray-400">
<?= date("d M Y", strtotime($row['created_at'])) ?>
</td>

<td class="p-3 text-center">
<?php if ($row['status'] !== 'approved'): ?>
<form method="POST" onsubmit="return confirm('Delete this device?');">
<input type="hidden" name="binding_id" value="<?= $row['id'] ?>">
<button name="delete_binding"
        class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-xs">
Delete
</button>
</form>
<?php else: ?>
<span class="text-gray-500 text-xs">Locked</span>
<?php endif; ?>
</td>

</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>

</div>

<!-- AUTO REFRESH ON ERROR + INACTIVITY -->
<?php if ($is_error): ?>
<script>
let inactivityTimer;
const REFRESH_TIME = 8000; // ⏱ 8 seconds inactivity
function resetTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        location.reload();
    }, REFRESH_TIME);
}
resetTimer();
['mousemove','keydown','click','scroll','touchstart'].forEach(evt => {
    document.addEventListener(evt, resetTimer, true);
});
</script>
<?php endif; ?>

<!-- DUPLICATE ROW HIGHLIGHT + TEMPORARY TOP -->
<?php if ($highlight_row): ?>
<script>
let duplicateId = <?= (int)$highlight_row ?>;
let row = document.getElementById('binding-row-' + duplicateId);
if (row) {
    const tableBody = row.parentElement;

    // Find last approved row
    let approvedRows = [...tableBody.querySelectorAll('tr[data-priority="1"]')];
    let lastApproved = approvedRows[approvedRows.length - 1];

    // Move duplicate after approved rows
    if (lastApproved) tableBody.insertBefore(row, lastApproved.nextSibling);
    else tableBody.insertBefore(row, tableBody.firstChild);

    // Add highlight
    row.classList.add('bg-yellow-500', 'animate-pulse');

    // After 3s, remove highlight & return to original position
    setTimeout(() => {
        row.classList.remove('bg-yellow-500', 'animate-pulse');
        let allRows = [...tableBody.querySelectorAll('tr')];
        let originalIndex = parseInt(row.dataset.originalIndex);
        let targetIndex = allRows.findIndex(r => parseInt(r.dataset.originalIndex) > originalIndex);
        if (targetIndex === -1) tableBody.appendChild(row);
        else tableBody.insertBefore(row, allRows[targetIndex]);
    }, 8000);
}
</script>
<?php endif; ?>

</body>
</html>
