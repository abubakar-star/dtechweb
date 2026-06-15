<?php
// ================= DB CONNECTION =================

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Database connection failed");
}

// ================= PAYMENT STATS =================

$stats = $conn->query("
    SELECT
        COUNT(*) AS total_payments,
        SUM(amount) AS total_amount
    FROM payments
    WHERE status='completed'
")->fetch_assoc();

// ================= FILTERS =================

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? 'completed');
$method = trim($_GET['method'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

$where = ["p.status = 'completed'"];

if (!empty($search)) {

    $search = $conn->real_escape_string($search);

    $where[] = "(
        u.username LIKE '%$search%'
        OR u.account_number LIKE '%$search%'
        OR u.phone_number LIKE '%$search%'
        OR p.transaction_id LIKE '%$search%'
    )";
}

if (!empty($method)) {

    $method = $conn->real_escape_string($method);

    $where[] = "p.payment_method = '$method'";
}

if (!empty($from)) {
    $where[] = "DATE(p.payment_date) >= '$from'";
}

if (!empty($to)) {
    $where[] = "DATE(p.payment_date) <= '$to'";
}

$whereSql = implode(' AND ', $where);


// ================= PAGINATION =================

$recordsPerPage =
    isset($_GET['per_page'])
    ? (int)$_GET['per_page']
    : 25;

$page = isset($_GET['page'])
    ? max(1, (int)$_GET['page'])
    : 1;

$offset = ($page - 1) * $recordsPerPage;

$countResult = $conn->query("
    SELECT COUNT(*) total

    FROM payments p

    INNER JOIN users u
        ON p.user_id = u.id

    WHERE $whereSql
");

$totalRecords = $countResult->fetch_assoc()['total'];

$totalPages = ceil(
    $totalRecords / $recordsPerPage
);

// ================= FETCH COMPLETED PAYMENTS =================

$payments = $conn->query("
    SELECT
        p.id,
        p.viewed,
        p.amount,
        p.payment_method,
        p.transaction_id,
        p.reference,
        p.invoice_number,
        p.payment_date,

        u.username,
        u.first_name,
        u.last_name,
        u.phone_number,
        u.account_number

    FROM payments p

    INNER JOIN users u
        ON p.user_id = u.id

    WHERE $whereSql

    ORDER BY p.payment_date DESC
LIMIT $recordsPerPage
OFFSET $offset
");

$clients = $conn->query("
    SELECT DISTINCT
        u.id,
        u.first_name,
        u.last_name,
        u.account_number
    FROM users u
    INNER JOIN payments p
        ON p.user_id = u.id
    WHERE p.status = 'completed'
    ORDER BY u.first_name, u.last_name
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>

@keyframes glowPulse {

    0% {
        box-shadow: 0 0 5px rgba(34,197,94,.4);
    }

    50% {
        box-shadow: 0 0 20px rgba(34,197,94,.9);
    }

    100% {
        box-shadow: 0 0 5px rgba(34,197,94,.4);
    }
}

.new-payment {

    background: #f0fdf4;

    animation:
        glowPulse 1.5s infinite;
}

</style>
</head>

<body class="bg-slate-100 min-h-screen p-6">

<!-- HEADER -->
<div class="flex justify-between items-center mb-6">

    <div>
        <h1 class="text-3xl font-bold text-slate-800">
            Payments Dashboard
        </h1>

        <p class="text-slate-500">
            Successfully completed customer payments
        </p>
    </div>

    <div class="relative">

        <button
            onclick="toggleClientDropdown()"
            class="bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700"
        >
            View Client ▼
        </button>

        <div
            id="clientDropdown"
            class="hidden absolute right-0 mt-2 w-80 bg-white border rounded-xl shadow-lg z-50 max-h-96 overflow-y-auto"
        >

            <div class="p-3 border-b">

                <input
                    type="text"
                    id="clientSearch"
                    placeholder="Search Client..."
                    class="w-full border rounded-lg px-3 py-2"
                    onkeyup="filterClients()"
                >

            </div>

            <?php while($client = $clients->fetch_assoc()): ?>

                <button
                    onclick="openClientModal(<?= $client['id'] ?>)"
                    class="client-item block w-full text-left px-4 py-3 hover:bg-slate-50"
                    data-name="<?= strtolower(
                        $client['first_name'].' '.
                        $client['last_name'].' '.
                        $client['account_number']
                    ) ?>"
                >

                    <div class="font-medium">
                        <?= htmlspecialchars(
                            $client['first_name'].' '.
                            $client['last_name']
                        ) ?>
                    </div>

                    <div class="text-xs text-slate-500">
                        <?= htmlspecialchars(
                            $client['account_number']
                        ) ?>
                    </div>

                </button>

            <?php endwhile; ?>

        </div>

    </div>

</div>

<!-- STATS -->
<div class="grid md:grid-cols-2 gap-6 mb-6">

    <div class="bg-white rounded-2xl shadow-sm p-6 border">

        <div class="text-slate-500 text-sm">
            Total Completed Payments
        </div>

        <div class="text-3xl font-bold text-green-600 mt-2">
            <?= number_format($stats['total_payments']) ?>
        </div>

    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6 border">

        <div class="text-slate-500 text-sm">
            Total Revenue
        </div>

        <div class="text-3xl font-bold text-blue-600 mt-2">
            KES <?= number_format($stats['total_amount'], 2) ?>
        </div>

    </div>

</div>

<!-- SEARCH & FILTERS -->

<div class="bg-white rounded-2xl shadow-sm border p-6 mb-6">

<form method="GET">

<div class="grid md:grid-cols-3 lg:grid-cols-6 gap-4">

    <!-- SEARCH -->

    <input
        type="text"
        name="search"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Search Client..."
        class="border rounded-xl px-4 py-2"
    >

    <!-- STATUS -->

    <select
        name="status"
        class="border rounded-xl px-4 py-2"
    >

        <option value="completed">
            Completed
        </option>

    </select>

    <!-- PAYMENT METHOD -->

    <select
        name="method"
        class="border rounded-xl px-4 py-2"
    >

        <option value="">
            All Methods
        </option>

        <option
            value="mpesa"
            <?= $method == 'mpesa' ? 'selected' : '' ?>
        >
            M-Pesa
        </option>

        <option
            value="bank"
            <?= $method == 'bank' ? 'selected' : '' ?>
        >
            Bank
        </option>

        <option
            value="cash"
            <?= $method == 'cash' ? 'selected' : '' ?>
        >
            Cash
        </option>

    </select>

    <select
    name="per_page"
    class="border rounded-xl px-4 py-2"
>
    <option value="25">25 Rows</option>
    <option value="50">50 Rows</option>
    <option value="100">100 Rows</option>
</select>

    <!-- FROM DATE -->

    <input
        type="date"
        name="from"
        value="<?= htmlspecialchars($from) ?>"
        class="border rounded-xl px-4 py-2"
    >

    <!-- TO DATE -->

    <input
        type="date"
        name="to"
        value="<?= htmlspecialchars($to) ?>"
        class="border rounded-xl px-4 py-2"
    >

    <!-- FILTER BUTTON -->

    <button
        type="submit"
        class="bg-blue-600 text-white rounded-xl px-4 py-2 hover:bg-blue-700"
    >
        Apply Filters
    </button>
    <a
    href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>"
    class="bg-gray-300 rounded-xl px-4 py-2 text-center hover:bg-gray-400"
>
    Reset
</a>

</div>

</form>

</div>

<div class="px-6 py-4 border-b flex justify-between items-center">

    <h2 class="font-semibold text-slate-800">
        Recent Completed Payments
    </h2>

    <span class="text-sm text-slate-500">

        Showing

        <?= min(
            $offset + 1,
            $totalRecords
        ) ?>

        -

        <?= min(
            $offset + $recordsPerPage,
            $totalRecords
        ) ?>

        of

        <?= number_format(
            $totalRecords
        ) ?>

        payments

    </span>

</div>

<!-- PAYMENTS TABLE -->
<div class="bg-white rounded-2xl shadow-sm border overflow-hidden">

    <div class="px-6 py-4 border-b">

        <h2 class="font-semibold text-slate-800">
            Recent Completed Payments
        </h2>

    </div>

    <div class="overflow-x-auto">

        <table class="w-full text-sm">

            <thead class="bg-slate-50">

            <tr class="text-slate-600">

                <th class="text-left p-4">Client</th>
                <th class="text-left p-4">Account No.</th>
                <th class="text-left p-4">Phone</th>
                <th class="text-left p-4">Amount</th>
                <th class="text-left p-4">Method</th>
                <th class="text-left p-4">Transaction ID</th>
                <th class="text-left p-4">Reference</th>
                <th class="text-left p-4">Date</th>
                <th class="text-left p-4">Status</th>

            </tr>

            </thead>

            <tbody>

            <?php while($payment = $payments->fetch_assoc()): ?>

            <tr
    data-payment-id="<?= $payment['id'] ?>"
    class="border-t hover:bg-slate-50
    <?= !$payment['viewed']
        ? 'new-payment'
        : '' ?>"
>

                <td class="p-4">

                    <div class="font-semibold text-slate-800">
                        <?= htmlspecialchars(
                            trim(
                                $payment['first_name'].' '.
                                $payment['last_name']
                            )
                        ) ?>
                    </div>

                    <div class="text-xs text-slate-500">
                        <?= htmlspecialchars($payment['username']) ?>
                    </div>

                </td>

                <td class="p-4">
                    <?= htmlspecialchars($payment['account_number']) ?>
                </td>

                <td class="p-4">
                    <?= htmlspecialchars($payment['phone_number']) ?>
                </td>

                <td class="p-4 font-bold text-green-600">
                    KES <?= number_format($payment['amount'], 2) ?>
                </td>

                <td class="p-4">
                    <?= htmlspecialchars($payment['payment_method']) ?>
                </td>

                <td class="p-4 font-mono text-xs">
                    <?= htmlspecialchars($payment['transaction_id']) ?>
                </td>

                <td class="p-4 font-mono text-xs">
                    <?= htmlspecialchars(
                        $payment['reference']
                        ?: $payment['invoice_number']
                    ) ?>
                </td>

                <td class="p-4">
                    <?= date(
                        "d M Y H:i",
                        strtotime($payment['payment_date'])
                    ) ?>
                </td>

                <td class="p-4">

                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                        Completed
                    </span>

                </td>

            </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

        <?php if($totalPages > 1): ?>

<div class="flex justify-center items-center gap-2 p-6 border-t">

    <!-- PREVIOUS -->

    <?php if($page > 1): ?>

        <a
            href="?<?= http_build_query(
                array_merge(
                    $_GET,
                    ['page' => $page - 1]
                )
            ) ?>"
            class="px-4 py-2 border rounded-lg hover:bg-slate-100"
        >
            Previous
        </a>

    <?php endif; ?>

    <!-- PAGE NUMBERS -->

    <?php for(
        $i = 1;
        $i <= $totalPages;
        $i++
    ): ?>

        <a
            href="?<?= http_build_query(
                array_merge(
                    $_GET,
                    ['page' => $i]
                )
            ) ?>"
            class="px-4 py-2 rounded-lg
            <?= $page == $i
                ? 'bg-blue-600 text-white'
                : 'border hover:bg-slate-100' ?>"
        >
            <?= $i ?>
        </a>

    <?php endfor; ?>

    <!-- NEXT -->

    <?php if($page < $totalPages): ?>

        <a
            href="?<?= http_build_query(
                array_merge(
                    $_GET,
                    ['page' => $page + 1]
                )
            ) ?>"
            class="px-4 py-2 border rounded-lg hover:bg-slate-100"
        >
            Next
        </a>

    <?php endif; ?>

</div>

<?php endif; ?>

    </div>

</div>

<div
    id="clientModal"
    class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50"
>

    <div class="bg-white rounded-2xl w-full max-w-5xl p-6 max-h-[90vh] overflow-y-auto">

        <div class="flex justify-between items-center mb-4">

            <h2 class="text-xl font-bold">
                Client Payment History
            </h2>

            <button
                onclick="closeClientModal()"
                class="text-gray-500 text-xl"
            >
                ✕
            </button>

        </div>

        <div id="clientModalContent">

            Loading...

        </div>

    </div>

</div>

<script>

function toggleClientDropdown() {

    document
        .getElementById('clientDropdown')
        .classList
        .toggle('hidden');
}

function filterClients() {

    const search =
        document
            .getElementById('clientSearch')
            .value
            .toLowerCase();

    const clients =
        document.querySelectorAll('.client-item');

    clients.forEach(client => {

        const name =
            client.dataset.name;

        client.style.display =
            name.includes(search)
            ? 'block'
            : 'none';
    });
}

function openClientModal(userId) {

    document
        .getElementById('clientDropdown')
        .classList
        .add('hidden');

    document
        .getElementById('clientModal')
        .classList
        .remove('hidden');

    fetch(
        'get_client_payments.php?user_id=' +
        userId
    )
    .then(response => response.text())
    .then(html => {

        document
            .getElementById(
                'clientModalContent'
            )
            .innerHTML = html;
    });
}

function closeClientModal() {

    document
        .getElementById('clientModal')
        .classList
        .add('hidden');
}

</script>

<script>

let userActive = false;
let pageVisible = true;

const pendingTimers = new Map();
const seenIds = new Set();

const activateUser = () => {
    userActive = true;
};

['mousemove', 'keydown', 'scroll', 'click', 'touchstart'].forEach(event => {
    window.addEventListener(event, activateUser, {
        once: true,
        passive: true
    });
});

// Tab visibility
document.addEventListener('visibilitychange', () => {
    pageVisible = !document.hidden;

    if (!pageVisible) {
        pendingTimers.forEach(timer => clearTimeout(timer));
        pendingTimers.clear();
    }
});

// Window focus
window.addEventListener('blur', () => {
    pendingTimers.forEach(timer => clearTimeout(timer));
    pendingTimers.clear();
});


const observer = new IntersectionObserver((entries) => {

    entries.forEach(entry => {

        const row = entry.target;
        const id = row.dataset.paymentId;

        if (seenIds.has(id)) return;

        // Row left viewport -> cancel timer
        if (!entry.isIntersecting) {

            if (pendingTimers.has(id)) {
                clearTimeout(pendingTimers.get(id));
                pendingTimers.delete(id);
            }

            return;
        }

        if (!userActive || !pageVisible) return;

        // Avoid multiple timers
        if (pendingTimers.has(id)) return;

        const timer = setTimeout(() => {

            pendingTimers.delete(id);

            if (
                seenIds.has(id) ||
                document.hidden ||
                !row.isConnected
            ) {
                return;
            }

            seenIds.add(id);

            row.classList.remove('new-payment');

            observer.unobserve(row);

            fetch('mark_payments_viewed.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ids: [id]
                })
            });

        }, 5000);

        pendingTimers.set(id, timer);

    });

}, {
    threshold: 0.5
});

document
    .querySelectorAll('.new-payment')
    .forEach(row => {
        observer.observe(row);
    });

</script>

</body>
</html>
