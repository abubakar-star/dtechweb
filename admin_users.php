<?php
// ================= DB CONNECTION =================

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

/*
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname     = "dlink_network";
*/
$conn = new mysqli($host, $username, $password, $dbname, $port);
if ($conn->connect_error) {
    die("DB Connection failed");
}

// ================= FETCH PACKAGES =================
$packages = [];

$result = $conn->query("
    SELECT id,
           package_name,
           speed,
           price,
           package_type
    FROM packages
    WHERE status='active'
");

while ($row = $result->fetch_assoc()) {
    $packages[] = $row;
}

// ================= FETCH ROUTERS =================
$routers = [];

$result = $conn->query("
    SELECT id, router_name, model, location, status
    FROM routers
    ORDER BY router_name
");

while ($row = $result->fetch_assoc()) {
    $routers[] = $row;
}

// ================= APPROVE USER =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {

    $stmt = $conn->prepare("
        UPDATE users
        SET verification_status = 'approved'
        WHERE id = ?
    ");

    $stmt->bind_param("i", $_POST['user_id']);
    $stmt->execute();
    $stmt->close();

    $success = "User approved successfully";
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
    $accountNumber    = trim($_POST['account_number']);
    $packageId        = !empty($_POST['package_id']) ? (int)$_POST['package_id'] : null;
    $routerId         = !empty($_POST['router_id']) ? (int)$_POST['router_id'] : 1;
    $dashboardOverride = $_POST['dashboard_override'];

    if ($username && $password && $email) {

       $stmt = $conn->prepare("
    INSERT INTO users (
        username,
        password,
        first_name,
        last_name,
        email,
        phone_number,
        account_number,
        connection_type,
        package_id,
        router_id,
        status,
        dashboard_override
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssssiiss",
    $username,
    $password,
    $first,
    $last,
    $email,
    $phone,
    $accountNumber,
    $connType,
    $packageId,
    $routerId,
    $status,
    $dashboardOverride
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
    SELECT
        users.id,
        users.username,
        users.password,
        users.phone_number,
        users.account_created_at,
        users.account_number,
        users.router_password,
        users.dashboard_override,
        users.status,
        users.verification_status,
        packages.package_name,
        routers.router_name
    FROM users
    LEFT JOIN packages ON users.package_id = packages.id
    LEFT JOIN routers ON users.router_id = routers.id
    ORDER BY users.id DESC
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
    <th class="p-2">Password</th>
    <th class="p-2">Phone</th>
    <th class="p-2">Account Number</th>
    <th class="p-2">Account Created</th>
    <th class="p-2">Package</th>
    
    <th class="p-2">Router Password</th>
    <th class="p-2">Dashboard Override</th>
    <th class="p-2">Status</th>
    <th class="p-2">Verification</th>
    <th class="p-2">Actions</th>
</tr>

</thead>
<tbody>
<?php while ($u = $users->fetch_assoc()): ?>
<tr class="border-t hover:bg-gray-50">

<td class="p-2"><?= $u['id'] ?></td>

<td class="p-2 font-semibold">
    <?= htmlspecialchars($u['username']) ?>
</td>

<td class="p-2 font-mono text-xs">
    <?= htmlspecialchars($u['password']) ?>
</td>

<td class="p-2">
    <?= htmlspecialchars($u['phone_number']) ?>
</td>

<td class="p-2">
    <?= htmlspecialchars($u['account_number']) ?>
</td>

<td class="p-2">
    <?= $u['account_created_at'] ?>
</td>

<td class="p-2">
    <?= htmlspecialchars($u['package_name'] ?? '-') ?>
</td>



<td class="p-2 font-mono text-xs">
    <?= htmlspecialchars($u['router_password']) ?>
</td>

<td class="p-2">
    <span class="px-2 py-1 rounded text-xs
    <?= $u['dashboard_override'] === 'on'
        ? 'bg-blue-200 text-blue-800'
        : 'bg-gray-200 text-gray-800' ?>">
        <?= ucfirst($u['dashboard_override']) ?>
    </span>
</td>

<td class="p-2">
    <span class="px-2 py-1 rounded text-xs
    <?= $u['status']=='active'
        ? 'bg-green-200 text-green-800'
        : 'bg-yellow-200 text-yellow-800' ?>">
        <?= $u['status'] ?>
    </span>
</td>

<td class="p-2">

<?php if($u['verification_status'] === 'approved'): ?>

    <span class="px-2 py-1 rounded text-xs bg-green-200 text-green-800">
        Approved
    </span>

<?php else: ?>

    <span class="px-2 py-1 rounded text-xs bg-yellow-200 text-yellow-800">
        Pending
    </span>

<?php endif; ?>

</td>

<td class="p-2 flex gap-2">

<?php if($u['verification_status'] !== 'approved'): ?>

<form method="POST">

    <input type="hidden" name="approve_user">
    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">

    <button
        onclick="return confirm('Approve this user?')"
        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs">

        Approve

    </button>

</form>

<?php endif; ?>

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

<form method="POST" id="createUserForm">
<input type="hidden" name="create_user">

<div class="grid grid-cols-2 gap-4">
<input name="username" placeholder="Username" class="border p-2 rounded" required>
<input name="password" placeholder="Password" class="border p-2 rounded" required>

<input name="first_name" placeholder="First Name" class="border p-2 rounded" required>
<input name="last_name" placeholder="Last Name" class="border p-2 rounded" required>

<input name="email" type="email" placeholder="Email" class="border p-2 rounded col-span-2" required>
<input name="phone" placeholder="Phone Number" class="border p-2 rounded col-span-2" required>

<input
    name="account_number"
    placeholder="Account Number"
    class="border p-2 rounded col-span-2" required
>

<select id="connectionType" name="connection_type" class="border p-2 rounded" required>
    <option value="" selected disabled>Select Connection Type</option>
    <option value="home">Home</option>
    <option value="business">Business</option>
</select>

<select id="packageSelect" name="package_id" class="border p-2 rounded" required>
    <option value="" selected disabled>Select Package</option>

    <?php foreach ($packages as $package): ?>
        <option
            value="<?= $package['id'] ?>"
            data-type="<?= $package['package_type'] ?>"
        >
            <?= htmlspecialchars($package['package_name']) ?>
            (<?= htmlspecialchars($package['speed']) ?> -
            KES <?= number_format($package['price']) ?>)
        </option>
    <?php endforeach; ?>
</select>

<select name="router_id" class="border p-2 rounded" required>
    <option value="" selected disabled>Select Router</option>

    <?php foreach ($routers as $router): ?>
        <option value="<?= $router['id'] ?>">
            <?= htmlspecialchars($router['router_name']) ?>
            - <?= htmlspecialchars($router['location']) ?>
            (<?= htmlspecialchars($router['status']) ?>)
        </option>
    <?php endforeach; ?>
</select>

<select name="dashboard_override" class="border p-2 rounded" required>
    <option value="" selected disabled>Select Dashboard Override</option>
    <option value="off">Dashboard Override Off</option>
    <option value="on">Dashboard Override On</option>
</select>

<select name="status" class="border p-2 rounded" required>
    <option value="" selected disabled>Select Status</option>
    <option value="inactive">Inactive</option>
    <option value="active">Active</option>
    <option value="queued">Queued</option>
</select>
</div>

<div class="flex justify-end gap-3 mt-6">
<button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
<button
    id="createUserBtn"
    disabled
    class="px-4 py-2 bg-gray-400 text-white rounded cursor-not-allowed disabled:opacity-70"
>
    Create User
</button>
</div>
</form>
</div>
</div>

<script>
function filterPackages() {

    const type = document.getElementById('connectionType').value;
    const packageSelect = document.getElementById('packageSelect');

    Array.from(packageSelect.options).forEach(option => {

        if (!option.dataset.type) return;

        option.hidden = option.dataset.type !== type;
    });

    packageSelect.value = '';
}

document.getElementById('connectionType')
    .addEventListener('change', filterPackages);

filterPackages();
</script>

<script>
function openModal() {
    document.getElementById('modal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('modal').classList.add('hidden');
}
</script>

<script>
const form = document.getElementById('createUserForm');
const submitBtn = document.getElementById('createUserBtn');

function validateForm() {

    const requiredFields = form.querySelectorAll('[required]');

    let allFilled = true;

    requiredFields.forEach(field => {

        if (!field.value || field.value.trim() === '') {
            allFilled = false;
        }

    });

    submitBtn.disabled = !allFilled;

    if (allFilled) {
        submitBtn.classList.remove(
            'bg-gray-400',
            'cursor-not-allowed'
        );

        submitBtn.classList.add(
            'bg-blue-600',
            'hover:bg-blue-700'
        );
    } else {
        submitBtn.classList.remove(
            'bg-blue-600',
            'hover:bg-blue-700'
        );

        submitBtn.classList.add(
            'bg-gray-400',
            'cursor-not-allowed'
        );
    }
}

form.addEventListener('input', validateForm);
form.addEventListener('change', validateForm);

validateForm();
</script>

</body>
</html>
