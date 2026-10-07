<?php
/* ================= DB CONNECTION ================= */
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("Database connection failed");
}

/* ================= HANDLE ACTION ================= */
if (isset($_POST['action'], $_POST['request_id'], $_POST['user_id'])) {

    $request_id = (int) $_POST['request_id'];
    $user_id    = (int) $_POST['user_id'];
    $action     = $_POST['action'];

    if ($action === 'approve') {

        // Fetch requested password
        $stmt = $conn->prepare(
            "SELECT new_password FROM router_password_requests WHERE id=?"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $newPassword = $res['new_password'] ?? '';

        if ($newPassword) {
            // Update user's router password
            $stmt = $conn->prepare(
                "UPDATE users SET router_password=? WHERE id=?"
            );
            $stmt->bind_param("si", $newPassword, $user_id);
            $stmt->execute();

            // Mark request as completed
            $stmt = $conn->prepare(
                "UPDATE router_password_requests SET status='changed' WHERE id=?"
            );
            $stmt->bind_param("i", $request_id);
            $stmt->execute();


/* 4️⃣ FETCH USER PHONE + NAME */
$stmt = $conn->prepare(
    "SELECT first_name, phone_number FROM users WHERE id=?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user['phone_number']) {

    /* 5️⃣ SEND TO VPS (NON-BLOCKING) */
    $payload = json_encode([
        'secret'   => 'MY_SECRET_123',
        'name'     => $user['first_name'],
        'phone'    => $user['phone_number'],
        'password' => $newPassword
    ]);

    $ch = curl_init("http://162.245.191.109/send_user_sms.php");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 3
    ]);

    curl_exec($ch);
    curl_close($ch);
}




            
        }

    } elseif ($action === 'reject') {

        $stmt = $conn->prepare(
            "UPDATE router_password_requests SET status='failed' WHERE id=?"
        );
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
    }

    header("Location: admin_password_requests.php");
    exit();
}

/* ================= FETCH REQUESTS ================= */
$sql = "
SELECT r.id, r.user_id, r.new_password, r.status, r.created_at,
       u.first_name
FROM router_password_requests r
JOIN users u ON u.id = r.user_id
ORDER BY r.created_at DESC
";

$requests = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin – Router Password Requests</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

<h1 class="text-2xl font-bold mb-6 text-blue-700">
Router Password Requests (Open Admin)
</h1>

<div class="bg-white rounded-xl shadow p-4 overflow-x-auto">
<table class="w-full text-sm border">
<thead class="bg-gray-100">
<tr>
  <th class="p-2 border">User</th>
  <th class="p-2 border">Requested Password</th>
  <th class="p-2 border">Status</th>
  <th class="p-2 border">Requested At</th>
  <th class="p-2 border">Action</th>
</tr>
</thead>

<tbody>
<?php while ($row = $requests->fetch_assoc()): ?>
<tr>
  <td class="p-2 border font-semibold">
    <?= htmlspecialchars($row['first_name']) ?>
  </td>

  <td class="p-2 border font-mono">
    <?= htmlspecialchars($row['new_password']) ?>
  </td>

  <td class="p-2 border font-semibold
      <?= $row['status']=='pending'?'text-yellow-600':'' ?>
      <?= $row['status']=='changed'?'text-green-600':'' ?>
      <?= $row['status']=='failed'?'text-red-600':'' ?>">
    <?= ucfirst($row['status']) ?>
  </td>

  <td class="p-2 border">
    <?= $row['created_at'] ?>
  </td>

  <td class="p-2 border">
<?php if ($row['status'] === 'pending'): ?>
<form method="POST" class="flex gap-2">
  <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
  <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">

  <button name="action" value="approve"
    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded">
    Approve
  </button>

  <button name="action" value="reject"
    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">
    Reject
  </button>
</form>
<?php else: ?>
<span class="text-gray-400">—</span>
<?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</body>
</html>
