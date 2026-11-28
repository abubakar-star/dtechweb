<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

if (empty($_SESSION['admin_billing_csrf'])) {
    $_SESSION['admin_billing_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_billing_csrf'];
$success = '';
$error = '';

function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($csrf, (string)$_POST['csrf_token'])) {
            throw new Exception('Security check failed. Refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'save_package') {
            $id = (int)($_POST['package_id'] ?? 0);
            $name = trim((string)($_POST['package_name'] ?? ''));
            $speed = trim((string)($_POST['speed'] ?? ''));
            $priceRaw = trim((string)($_POST['price'] ?? ''));
            $type = strtolower(trim((string)($_POST['package_type'] ?? '')));
            $status = (string)($_POST['status'] ?? 'active');

            if ($name === '' || strlen($name) > 100) throw new Exception('Enter a package name (maximum 100 characters).');
            if ($speed === '' || strlen($speed) > 50) throw new Exception('Enter the package speed.');
            if (!is_numeric($priceRaw) || (float)$priceRaw < 0) throw new Exception('Enter a valid package price.');
            if (!preg_match('/^[a-z0-9_-]{1,30}$/', $type)) throw new Exception('Choose a valid connection type.');
            if (!in_array($status, ['active', 'inactive'], true)) throw new Exception('Invalid package status.');
            $price = (float)$priceRaw;

            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE packages SET package_name = ?, speed = ?, price = ?, package_type = ?, status = ? WHERE id = ?');
                $stmt->bind_param('ssdssi', $name, $speed, $price, $type, $status, $id);
                $stmt->execute();
                $stmt->close();
                $success = 'Package updated successfully.';
            } else {
                $stmt = $conn->prepare('INSERT INTO packages (package_name, speed, price, package_type, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('ssdss', $name, $speed, $price, $type, $status);
                $stmt->execute();
                $stmt->close();
                $success = 'New package added successfully.';
            }
        } elseif ($action === 'add_charge') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $chargeName = trim((string)($_POST['charge_name'] ?? ''));
            $amountRaw = trim((string)($_POST['amount'] ?? ''));
            if ($userId < 1) throw new Exception('Select a customer.');
            if ($chargeName === '' || strlen($chargeName) > 150) throw new Exception('Enter a charge description (maximum 150 characters).');
            if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) throw new Exception('Enter an amount greater than zero.');
            $check = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $check->bind_param('i', $userId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$exists) throw new Exception('The selected customer could not be found.');
            $amount = (float)$amountRaw;
            $stmt = $conn->prepare("INSERT INTO extra_charges (user_id, charge_name, amount, status) VALUES (?, ?, ?, 'pending')");
            $stmt->bind_param('isd', $userId, $chargeName, $amount);
            $stmt->execute();
            $stmt->close();
            $success = 'Extra charge added. It will appear as pending on the customer subscription page.';
        } else {
            throw new Exception('Unknown action.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$packages = [];
$result = $conn->query("SELECT id, package_name, speed, price, package_type, status FROM packages ORDER BY package_type, price, package_name");
while ($row = $result->fetch_assoc()) $packages[] = $row;

$users = [];
$result = $conn->query("SELECT id, first_name, last_name, phone_number, connection_type FROM users ORDER BY first_name, last_name");
while ($row = $result->fetch_assoc()) $users[] = $row;

$connectionTypes = ['home', 'business'];
$result = $conn->query("SELECT DISTINCT LOWER(connection_type) AS connection_type FROM users WHERE connection_type IS NOT NULL AND connection_type <> ''");
while ($row = $result->fetch_assoc()) {
    $value = strtolower(trim($row['connection_type']));
    if (preg_match('/^[a-z0-9_-]{1,30}$/', $value) && !in_array($value, $connectionTypes, true)) $connectionTypes[] = $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Packages & Extra Charges | D-LINK Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<header class="bg-slate-900 text-white px-4 py-4 shadow"><div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-bold">Packages & Extra Charges</h1><p class="text-sm text-slate-300">Manage customer billing items</p></div><a href="tosha.php" class="rounded-lg bg-slate-700 hover:bg-slate-600 px-4 py-2 text-sm">← Admin Panel</a></div></header>
<main class="max-w-6xl mx-auto p-4 md:p-6 space-y-6">
<?php if ($success): ?><div class="rounded-lg bg-green-100 border border-green-300 text-green-800 p-3"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="rounded-lg bg-red-100 border border-red-300 text-red-800 p-3"><?= h($error) ?></div><?php endif; ?>
<div class="grid lg:grid-cols-2 gap-6">
<section class="bg-white rounded-xl shadow p-5">
<h2 class="text-lg font-bold mb-1">Add extra charge to a customer</h2><p class="text-sm text-slate-500 mb-4">The charge is created with <b>pending</b> status and is collected through the existing customer payment flow.</p>
<form method="post" class="space-y-4">
<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="add_charge">
<div><label class="block text-sm font-medium mb-1">Customer</label><select name="user_id" required class="w-full border rounded-lg p-3 bg-white"><option value="">Select customer</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= h(trim($u['first_name'].' '.$u['last_name'])) ?> — <?= h($u['phone_number']) ?> (ID: <?= (int)$u['id'] ?>)</option><?php endforeach; ?></select></div>
<div><label class="block text-sm font-medium mb-1">Charge description</label><input name="charge_name" maxlength="150" required placeholder="e.g. Installation fee, Router replacement" class="w-full border rounded-lg p-3"></div>
<div><label class="block text-sm font-medium mb-1">Amount (KES)</label><input name="amount" type="number" min="0.01" step="0.01" required placeholder="500" class="w-full border rounded-lg p-3"></div>
<button class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold p-3">Add Extra Charge</button>
</form>
</section>
<section class="bg-white rounded-xl shadow p-5" id="package-form">
<h2 class="text-lg font-bold mb-1">Add or edit package</h2><p class="text-sm text-slate-500 mb-4">Package type must match the customer's connection type.</p>
<form method="post" class="space-y-3" id="packageForm">
<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save_package"><input type="hidden" name="package_id" id="package_id" value="0">
<div><label class="block text-sm font-medium mb-1">Package name</label><input name="package_name" id="package_name" maxlength="100" required class="w-full border rounded-lg p-3" placeholder="e.g. Home 10 Mbps"></div>
<div class="grid grid-cols-2 gap-3"><div><label class="block text-sm font-medium mb-1">Speed</label><input name="speed" id="speed" maxlength="50" required class="w-full border rounded-lg p-3" placeholder="10 Mbps"></div><div><label class="block text-sm font-medium mb-1">Price (KES)</label><input name="price" id="price" type="number" min="0" step="0.01" required class="w-full border rounded-lg p-3" placeholder="1500"></div></div>
<div class="grid grid-cols-2 gap-3"><div><label class="block text-sm font-medium mb-1">Connection type</label><select name="package_type" id="package_type" required class="w-full border rounded-lg p-3"><?php foreach ($connectionTypes as $type): ?><option value="<?= h($type) ?>"><?= h(ucfirst($type)) ?></option><?php endforeach; ?></select></div><div><label class="block text-sm font-medium mb-1">Status</label><select name="status" id="package_status" class="w-full border rounded-lg p-3"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div>
<div class="flex gap-2"><button id="savePackageBtn" class="flex-1 rounded-lg bg-green-600 hover:bg-green-700 text-white font-semibold p-3">Save Package</button><button type="button" onclick="resetPackageForm()" class="rounded-lg bg-slate-200 hover:bg-slate-300 px-4">Clear</button></div>
</form>
</section>
</div>
<section class="bg-white rounded-xl shadow overflow-hidden">
<div class="p-5 border-b"><h2 class="text-lg font-bold">Existing packages</h2><p class="text-sm text-slate-500">Use Edit to load a package into the form. Set a package to inactive to remove it from customer selection without deleting historical references.</p></div>
<div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="bg-slate-50 text-slate-600"><tr><th class="p-3">Package</th><th class="p-3">Speed</th><th class="p-3">Price</th><th class="p-3">Type</th><th class="p-3">Status</th><th class="p-3">Action</th></tr></thead><tbody>
<?php if (!$packages): ?><tr><td colspan="6" class="p-5 text-center text-slate-500">No packages found.</td></tr><?php endif; ?>
<?php foreach ($packages as $p): ?><tr class="border-t hover:bg-slate-50"><td class="p-3 font-medium"><?= h($p['package_name']) ?></td><td class="p-3"><?= h($p['speed']) ?></td><td class="p-3">KES <?= number_format((float)$p['price'], 2) ?></td><td class="p-3"><?= h($p['package_type']) ?></td><td class="p-3"><span class="px-2 py-1 rounded-full text-xs <?= $p['status']==='active'?'bg-green-100 text-green-700':'bg-slate-200 text-slate-600' ?>"><?= h($p['status']) ?></span></td><td class="p-3"><button type="button" class="text-indigo-600 hover:underline font-semibold" onclick='editPackage(<?= json_encode($p, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>Edit</button></td></tr><?php endforeach; ?>
</tbody></table></div>
</section>
</main>
<script>
function editPackage(p) {
 document.getElementById('package_id').value = p.id;
 document.getElementById('package_name').value = p.package_name;
 document.getElementById('speed').value = p.speed;
 document.getElementById('price').value = p.price;
 const type = document.getElementById('package_type');
 if (![...type.options].some(o => o.value === p.package_type)) { const o = new Option(p.package_type, p.package_type); type.add(o); }
 type.value = p.package_type;
 document.getElementById('package_status').value = p.status;
 document.getElementById('savePackageBtn').textContent = 'Update Package';
 document.getElementById('package-form').scrollIntoView({behavior:'smooth'});
}
function resetPackageForm() {
 document.getElementById('packageForm').reset();
 document.getElementById('package_id').value = '0';
 document.getElementById('savePackageBtn').textContent = 'Save Package';
}
</script>
</body></html>
