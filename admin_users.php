<?php
// ================= DB CONNECTION =================

$servername = "sql313.infinityfree.com";
$db_username = "if0_39741603";
$db_password = "mkala3771";
$dbname     = "if0_39741603_dlink_network";

/*
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname     = "dlink_network";
*/
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("DB Connection failed");
}

// ================= DELETE USER =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $_POST['user_id']);
    $stmt->execute();
    $stmt->close();
    $success = "User deleted successfully";
}


// ================= CREATE USER =================
$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // stored as-is (same as your DB)
    $first    = trim($_POST['first_name']);
    $last     = trim($_POST['last_name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $connType = $_POST['connection_type'];
    $status   = $_POST['status'];

    if ($username && $password && $email) {

        $stmt = $conn->prepare("
            INSERT INTO users 
            (username, password, first_name, last_name, email, phone_number, connection_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssss",
            $username,
            $password,
            $first,
            $last,
            $email,
            $phone,
            $connType,
            $status
        );

        if ($stmt->execute()) {
            $success = "User created successfully";
        } else {
            $error = "Username or Email already exists";
        }
        $stmt->close();
    } else {
        $error = "Username, password and email are required";
    }
}

// ================= FETCH USERS =================
$users = $conn->query("
    SELECT id, username, first_name, last_name, email, phone_number,
           connection_type, status, created_at, Expiry, visit_count
    FROM users
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Users</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">

<!-- HEADER -->
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-blue-700">Admin – Users Management</h1>
    <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        + Add New User
    </button>
</div>

<?php if ($success): ?>
<div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= $error ?></div>
<?php endif; ?>

<!-- USERS TABLE -->
<div class="bg-white shadow rounded overflow-x-auto">
<table class="w-full text-sm">
<thead class="bg-gray-200 text-gray-700">
<tr>
<th class="p-2">ID</th>
<th class="p-2">Username</th>
<th class="p-2">Name</th>
<th class="p-2">Email</th>
<th class="p-2">Phone</th>
<th class="p-2">Type</th>
<th class="p-2">Status</th>
<th class="p-2">Created</th>
<th class="p-2">Expiry</th>
<th class="p-2">Visits</th>
<th class="p-2">Actions</th>

</tr>
</thead>
<tbody>
<?php while ($u = $users->fetch_assoc()): ?>
<tr class="border-t hover:bg-gray-50">
<td class="p-2"><?= $u['id'] ?></td>
<td class="p-2 font-semibold"><?= htmlspecialchars($u['username']) ?></td>
<td class="p-2"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></td>
<td class="p-2"><?= htmlspecialchars($u['email']) ?></td>
<td class="p-2"><?= htmlspecialchars($u['phone_number']) ?></td>
<td class="p-2"><?= $u['connection_type'] ?></td>
<td class="p-2">
<span class="px-2 py-1 rounded text-xs
<?= $u['status']=='active' ? 'bg-green-200 text-green-800' : 'bg-yellow-200 text-yellow-800' ?>">
<?= $u['status'] ?>
</span>
</td>
<td class="p-2"><?= $u['created_at'] ?></td>
<td class="p-2"><?= $u['Expiry'] ?></td>
<td class="p-2"><?= $u['visit_count'] ?></td>
<td class="p-2 flex gap-2">

    <!-- EDIT (redirects to edit page) -->
    <a href="edit_user.php?id=<?= $u['id'] ?>"
       class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-xs">
        Edit
    </a>

    <!-- DELETE -->
    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?')">
        <input type="hidden" name="delete_user">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <button class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs">
            Delete
        </button>
    </form>

</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<!-- MODAL -->
<div id="modal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center">
<div class="bg-white w-full max-w-lg rounded p-6">
<h2 class="text-lg font-bold mb-4">Create New User</h2>

<form method="POST">
<input type="hidden" name="create_user">

<div class="grid grid-cols-2 gap-4">
<input name="username" placeholder="Username" class="border p-2 rounded" required>
<input name="password" placeholder="Password" class="border p-2 rounded" required>

<input name="first_name" placeholder="First Name" class="border p-2 rounded">
<input name="last_name" placeholder="Last Name" class="border p-2 rounded">

<input name="email" type="email" placeholder="Email" class="border p-2 rounded col-span-2" required>
<input name="phone" placeholder="Phone Number" class="border p-2 rounded col-span-2">

<select name="connection_type" class="border p-2 rounded">
<option value="home">Home</option>
<option value="business">Business</option>
</select>

<select name="status" class="border p-2 rounded">
<option value="inactive">Inactive</option>
<option value="active">Active</option>
<option value="queued">Queued</option>
</select>
</div>

<div class="flex justify-end gap-3 mt-6">
<button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
<button class="px-4 py-2 bg-blue-600 text-white rounded">Create User</button>
</div>
</form>
</div>
</div>

<script>
function openModal() {
    document.getElementById('modal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('modal').classList.add('hidden');
}
</script>

</body>
</html>
