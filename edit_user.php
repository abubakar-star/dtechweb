<?php
// ================= DB CONNECTION =================
$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("DB Connection failed");
}

// ================= GET USER ID =================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID");
}

$user_id = (int)$_GET['id'];
$success = $error = "";

// ================= FETCH PACKAGES =================
$packages = [];
$res = $conn->query("SELECT id, package_name, price FROM packages ORDER BY package_name ASC");
while ($row = $res->fetch_assoc()) {
    $packages[] = $row;
}

// ================= FETCH ROUTERS =================
$routers = [];
$res = $conn->query("SELECT id, router_name FROM routers ORDER BY router_name ASC");
while ($row = $res->fetch_assoc()) {
    $routers[] = $row;
}



// ================= FETCH USER =================
$stmt = $conn->prepare("
   SELECT id,
       first_name,
       last_name,
       username,
       email,
       phone_number,
       account_number,
       router_password,
       dashboard_override,
       connection_type,
       status,
       package_id,
       router_id,
       created_at
FROM users
WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found");
}

// ================= UPDATE USER =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $passwordSQL = "";
    $params = [
    $_POST['first_name'],
    $_POST['last_name'],
    $_POST['username'],
    $_POST['email'],
    $_POST['phone_number'],
    $_POST['account_number'],
    $_POST['router_password'],
    $_POST['dashboard_override'],
    $_POST['connection_type'],
    $_POST['status'],
    $_POST['package_id'],
    $_POST['router_id'],
    $_POST['created_at']
];

$types = "ssssssssssiss"; // match the above order

    // Update password ONLY if filled (NO HASHING)
    if (!empty($_POST['password'])) {
        $passwordSQL = ", password = ?";
    $params[] = $_POST['password']; // s
    $types .= "s";
    }

    $params[] = $user_id;
    $types .= "i";

    $stmt = $conn->prepare("
       UPDATE users SET
    first_name = ?,
    last_name = ?,
    username = ?,
    email = ?,
    phone_number = ?,
    account_number = ?,
    router_password = ?,
    dashboard_override = ?,
    connection_type = ?,
    status = ?,
    package_id = ?,
    router_id = ?,
    created_at = ?
    $passwordSQL
WHERE id = ?
    ");

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $success = "User updated successfully";
    } else {
        $error = "Update failed";
    }

    $stmt->close();

    // Refresh user data
    header("Location: edit_user.php?id=".$user_id."&success=1");
    exit;
}

if (isset($_GET['success'])) {
    $success = "User updated successfully";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit User</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

<div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
<h1 class="text-xl font-bold text-blue-700 mb-4">
Edit User — <?= htmlspecialchars($user['username']) ?>
</h1>

<?php if ($success): ?>
<div class="bg-green-100 text-green-700 p-3 mb-4 rounded"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 text-red-700 p-3 mb-4 rounded"><?= $error ?></div>
<?php endif; ?>


<form method="POST" class="space-y-4">

<div class="grid grid-cols-2 gap-4">
<div>
<label class="text-sm">First Name</label>
<input name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>"
class="border p-2 rounded w-full">
</div>

<div>
<label class="text-sm">Last Name</label>
<input name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>"
class="border p-2 rounded w-full">
</div>
</div>

<div class="grid grid-cols-2 gap-4">
<div>
<label class="text-sm">Username</label>
<input name="username" required value="<?= htmlspecialchars($user['username']) ?>"
class="border p-2 rounded w-full">
</div>

<div>
<label class="text-sm">Password (leave blank to keep)</label>
<input name="password" type="text"
class="border p-2 rounded w-full">
</div>
</div>

<div>
<label class="text-sm">Email</label>
<input name="email" type="email" required
value="<?= htmlspecialchars($user['email']) ?>"
class="border p-2 rounded w-full">
</div>

<div>
<label class="text-sm">Phone Number</label>
<input name="phone_number"
value="<?= htmlspecialchars($user['phone_number']) ?>"
class="border p-2 rounded w-full">
</div>

<div class="grid grid-cols-2 gap-4">

    <div>
        <label class="text-sm">Account Number</label>
        <input
            name="account_number"
            value="<?= htmlspecialchars($user['account_number']) ?>"
            class="border p-2 rounded w-full">
    </div>

    <div>
        <label class="text-sm">Router Password</label>
        <input
            name="router_password"
            value="<?= htmlspecialchars($user['router_password']) ?>"
            class="border p-2 rounded w-full">
    </div>

</div>

<div>
    <label class="text-sm">Dashboard Override</label>
    <select name="dashboard_override" class="border p-2 rounded w-full">
        <option value="off"
            <?= $user['dashboard_override'] == 'off' ? 'selected' : '' ?>>
            Off
        </option>

        <option value="on"
            <?= $user['dashboard_override'] == 'on' ? 'selected' : '' ?>>
            On
        </option>
    </select>
</div>

<div class="grid grid-cols-2 gap-4">
<div>
<label class="text-sm">Connection Type</label>
<select name="connection_type" class="border p-2 rounded w-full">
<option value="home" <?= $user['connection_type']=='home'?'selected':'' ?>>Home</option>
<option value="business" <?= $user['connection_type']=='business'?'selected':'' ?>>Business</option>
</select>
</div>

<div>
<label class="text-sm">Status</label>
<select name="status" class="border p-2 rounded w-full">
<option value="inactive" <?= $user['status']=='inactive'?'selected':'' ?>>Inactive</option>
<option value="queued" <?= $user['status']=='queued'?'selected':'' ?>>Queued</option>
<option value="active" <?= $user['status']=='active'?'selected':'' ?>>Active</option>
<option value="suspended" <?= $user['status']=='suspended'?'selected':'' ?>>Suspended</option>
<option value="terminated" <?= $user['status']=='terminated'?'selected':'' ?>>Terminated</option>
</select>
</div>
</div>

<div class="grid grid-cols-2 gap-4">
<div>
<label class="text-sm">Package</label>
<select name="package_id" required class="border p-2 rounded w-full">
    <?php foreach ($packages as $pkg): ?>
        <option value="<?= $pkg['id'] ?>" <?= $pkg['id'] == $user['package_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($pkg['package_name']) ?> — KES <?= number_format($pkg['price'], 2) ?>
        </option>
    <?php endforeach; ?>
</select>
</div>


<div>
<label class="text-sm">Router</label>
<select name="router_id" required class="border p-2 rounded w-full">
    <?php foreach ($routers as $router): ?>
        <option value="<?= $router['id'] ?>" <?= $router['id'] == $user['router_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($router['router_name']) ?>
        </option>
    <?php endforeach; ?>
</select>
</div>

</div>

<div>
<label class="text-sm">Created At</label>
<input name="created_at" type="datetime-local"
       value="<?= date('Y-m-d\TH:i', strtotime($user['created_at'])) ?>"
       class="border p-2 rounded w-full">
</div>


<div class="flex justify-between mt-6">
<a href="admin_users.php"
class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">
← Back
</a>

<button class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
Save Changes
</button>
</div>

</form>
</div>

</body>
</html>
