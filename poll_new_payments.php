<?php

header('Content-Type: application/json');

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

$lastId = (int)($_GET['last_id'] ?? 0);

$result = $conn->query("
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

    WHERE p.status='completed'
    AND p.id > $lastId

    ORDER BY p.id DESC
");

$rows = [];

while ($payment = $result->fetch_assoc()) {

    ob_start();
    ?>

<tr
    data-payment-id="<?= $payment['id'] ?>"
    class="border-t hover:bg-slate-50 <?= !$payment['viewed'] ? 'new-payment' : '' ?>"
>
    <td class="p-4">
        <div class="font-semibold text-slate-800">
            <?= htmlspecialchars(trim($payment['first_name'].' '.$payment['last_name'])) ?>
        </div>

        <div class="text-xs text-slate-500">
            <?= htmlspecialchars($payment['username']) ?>
        </div>
    </td>

    <td class="p-4"><?= htmlspecialchars($payment['account_number']) ?></td>
    <td class="p-4"><?= htmlspecialchars($payment['phone_number']) ?></td>

    <td class="p-4 font-bold text-green-600">
        KES <?= number_format($payment['amount'],2) ?>
    </td>

    <td class="p-4"><?= htmlspecialchars($payment['payment_method']) ?></td>

    <td class="p-4 font-mono text-xs">
        <?= htmlspecialchars($payment['transaction_id']) ?>
    </td>

    <td class="p-4 font-mono text-xs">
        <?= htmlspecialchars($payment['reference'] ?: $payment['invoice_number']) ?>
    </td>

    <td class="p-4">
        <?= date("d M Y H:i", strtotime($payment['payment_date'])) ?>
    </td>

    <td class="p-4">
        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
            Completed
        </span>
    </td>
</tr>

    <?php

    $rows[] = [
        'id' => $payment['id'],
        'html' => ob_get_clean()
    ];
}

echo json_encode($rows);