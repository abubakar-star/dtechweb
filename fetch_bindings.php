<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("sql313.infinityfree.com", "if0_39741603", "mkala3771", "if0_39741603_dlink_network");
if ($conn->connect_error) {
    die("DB error");
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$params = [];
$types = "";
$where = "WHERE 1";

if ($search !== "") {
    $where .= " AND CONCAT(u.first_name,' ',u.last_name) LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($status !== "") {
    $where .= " AND b.status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql = "
SELECT
    b.id,
    b.user_id,
    CONCAT(u.first_name,' ',u.last_name) AS full_name,
    b.device_name,
    b.ip_address,
    b.mac_address,
    b.status,
    b.created_at
FROM bindings b
JOIN users u ON u.id = b.user_id
$where
ORDER BY (b.status='pending') DESC, b.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<table class="min-w-full text-sm">
<thead class="bg-gray-700">
<tr>
    <th class="p-3 text-left">User ID</th>
    <th class="p-3 text-left">Name</th>
    <th class="p-3">Device</th>
    <th class="p-3">IP</th>
    <th class="p-3">MAC</th>
    <th class="p-3">Status</th>
    <th class="p-3">Date</th>
    <th class="p-3 text-center">Actions</th>
</tr>
</thead>

<tbody>
<?php if ($result->num_rows === 0): ?>
<tr>
<td colspan="8" class="p-4 text-center text-gray-400">
No results found
</td>
</tr>
<?php endif; ?>

<?php while ($row = $result->fetch_assoc()): ?>
<tr class="border-b border-gray-700 hover:bg-gray-700/40">

<td class="p-3"><?= $row['user_id'] ?></td>
<td class="p-3 text-blue-400 font-medium"><?= htmlspecialchars($row['full_name']) ?></td>
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
<?= date("d M Y H:i", strtotime($row['created_at'])) ?>
</td>

<td class="p-3 text-center">
<form method="POST" action="admin_action.php" class="flex gap-2 justify-center">
<input type="hidden" name="binding_id" value="<?= $row['id'] ?>">

<?php if ($row['status'] === 'pending'): ?>
<button name="approve" class="bg-green-600 px-3 py-1 rounded text-xs">Approve</button>
<button name="reject" class="bg-yellow-600 px-3 py-1 rounded text-xs">Reject</button>
<?php endif; ?>

<button name="delete"
onclick="return confirm('Delete permanently?')"
class="bg-red-600 px-3 py-1 rounded text-xs">Delete</button>
</form>
</td>

</tr>
<?php endwhile; ?>
</tbody>
</table>
