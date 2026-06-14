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

// ================= FETCH COMPLETED PAYMENTS =================

$payments = $conn->query("
    SELECT
        p.id,
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

    WHERE p.status='completed'

    ORDER BY p.payment_date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments</title>
<script src="https://cdn.tailwindcss.com"></script>
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

            <tr class="border-t hover:bg-slate-50">

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

    </div>

</div>

</body>
</html>