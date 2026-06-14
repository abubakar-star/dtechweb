<?php

// ================= DB CONNECTION =================

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli(
    $host,
    $username,
    $password,
    $dbname,
    $port
);

if ($conn->connect_error) {
    die("Database connection failed");
}

// ================= GET USER ID =================

$userId = isset($_GET['user_id'])
    ? (int)$_GET['user_id']
    : 0;

if ($userId <= 0) {
    exit("Invalid client.");
}

// ================= CLIENT DETAILS =================

$client = $conn->query("
    SELECT
        id,
        username,
        first_name,
        last_name,
        phone_number,
        account_number
    FROM users
    WHERE id = $userId
")->fetch_assoc();

if (!$client) {
    exit("Client not found.");
}

// ================= PAYMENT SUMMARY =================

$summary = $conn->query("
    SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(amount),0) AS total_paid
    FROM payments
    WHERE user_id = $userId
    AND status = 'completed'
")->fetch_assoc();

// ================= PAYMENT HISTORY =================

$payments = $conn->query("
    SELECT
        amount,
        payment_method,
        transaction_id,
        reference,
        invoice_number,
        payment_date
    FROM payments
    WHERE user_id = $userId
    AND status = 'completed'
    ORDER BY payment_date DESC
");
?>

<!-- CLIENT HEADER -->

<div class="mb-6">

    <h3 class="text-2xl font-bold text-slate-800">
        <?= htmlspecialchars(
            $client['first_name'] . ' ' .
            $client['last_name']
        ) ?>
    </h3>

    <div class="text-slate-500 mt-1">
        Username:
        <?= htmlspecialchars($client['username']) ?>
    </div>

    <div class="text-slate-500">
        Account:
        <?= htmlspecialchars($client['account_number']) ?>
    </div>

    <div class="text-slate-500">
        Phone:
        <?= htmlspecialchars($client['phone_number']) ?>
    </div>

</div>

<!-- SUMMARY CARDS -->

<div class="grid md:grid-cols-2 gap-4 mb-6">

    <div class="bg-slate-100 rounded-xl p-4">

        <div class="text-sm text-slate-500">
            Total Payments
        </div>

        <div class="text-3xl font-bold text-blue-600">
            <?= number_format(
                $summary['total_payments']
            ) ?>
        </div>

    </div>

    <div class="bg-slate-100 rounded-xl p-4">

        <div class="text-sm text-slate-500">
            Total Amount Paid
        </div>

        <div class="text-3xl font-bold text-green-600">
            KES <?= number_format(
                $summary['total_paid'],
                2
            ) ?>
        </div>

    </div>

</div>

<!-- PAYMENT HISTORY -->

<div class="border rounded-xl overflow-hidden">

    <div class="bg-slate-50 px-4 py-3 border-b">

        <h4 class="font-semibold">
            Payment History
        </h4>

    </div>

    <div class="overflow-x-auto">

        <table class="w-full text-sm">

            <thead class="bg-slate-100">

            <tr>

                <th class="text-left p-3">
                    Date
                </th>

                <th class="text-left p-3">
                    Amount
                </th>

                <th class="text-left p-3">
                    Method
                </th>

                <th class="text-left p-3">
                    Transaction ID
                </th>

                <th class="text-left p-3">
                    Reference
                </th>

            </tr>

            </thead>

            <tbody>

            <?php if ($payments->num_rows > 0): ?>

                <?php while ($payment = $payments->fetch_assoc()): ?>

                <tr class="border-t hover:bg-slate-50">

                    <td class="p-3">
                        <?= date(
                            "d M Y H:i",
                            strtotime(
                                $payment['payment_date']
                            )
                        ) ?>
                    </td>

                    <td class="p-3 font-bold text-green-600">
                        KES <?= number_format(
                            $payment['amount'],
                            2
                        ) ?>
                    </td>

                    <td class="p-3">
                        <?= htmlspecialchars(
                            $payment['payment_method']
                        ) ?>
                    </td>

                    <td class="p-3 font-mono text-xs">
                        <?= htmlspecialchars(
                            $payment['transaction_id']
                        ) ?>
                    </td>

                    <td class="p-3 font-mono text-xs">
                        <?= htmlspecialchars(
                            $payment['reference']
                            ?: $payment['invoice_number']
                        ) ?>
                    </td>

                </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="p-6 text-center text-slate-500"
                    >
                        No completed payments found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>