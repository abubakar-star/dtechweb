<?php
session_start();

require 'db.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$transaction_id = $_GET['id'] ?? '';

if (empty($transaction_id)) {
    die("Invalid invoice reference");
}

$sql = "
SELECT
    p.transaction_id,
    p.amount,
    p.payment_date,
    p.status,
    u.first_name,
    u.last_name,
    u.email,
    u.phone_number,
    pkg.package_name,
    pkg.speed
FROM payments p
INNER JOIN users u ON p.user_id = u.id
LEFT JOIN packages pkg ON u.package_id = pkg.id
WHERE p.transaction_id = ?
AND p.user_id = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $transaction_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Receipt not found");
}

$data = $result->fetch_assoc();

$customerName = trim($data['first_name'] . ' ' . $data['last_name']);
$amount = number_format($data['amount'], 2);
$datePaid = date('M d, Y', strtotime($data['payment_date']));
$package = htmlspecialchars($data['speed'] ?? 'Internet Package');

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body{
    font-family: Arial, sans-serif;
    font-size: 12px;
    color:#333;
}

.header{
    border-bottom:2px solid #ddd;
    padding-bottom:10px;
    margin-bottom:20px;
}

.company{
    color:#ea580c;
    font-size:24px;
    font-weight:bold;
}

.receipt{
    float:right;
    text-align:right;
}

.bill{
    margin-top:20px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th{
    background:#f3f4f6;
    text-align:left;
    padding:10px;
}

td{
    padding:10px;
    border-top:1px solid #ddd;
}

.total{
    margin-top:20px;
    text-align:right;
    font-size:18px;
    font-weight:bold;
    color:#15803d;
}

.paid{
    position:fixed;
    top:40%;
    left:20%;
    transform:rotate(-20deg);
    border:5px solid green;
    color:green;
    font-size:60px;
    font-weight:bold;
    padding:15px 40px;
    opacity:0.15;
}

.footer{
    margin-top:40px;
    text-align:center;
    color:#666;
}
</style>
</head>
<body>

<div class="paid">PAID</div>

<div class="header">
    <div class="company">
        D-Link Network Inc.
    </div>

    <div class="receipt">
        <h2>RECEIPT</h2>
        <strong>M-PESA REF:</strong> '.$data['transaction_id'].'<br>
        <strong>Date:</strong> '.$datePaid.'
    </div>
</div>

<div class="bill">
    <strong>BILL TO</strong><br><br>

    '.$customerName.'<br>
    '.$data['email'].'<br>
    '.$data['phone_number'].'
</div>

<table>
<tr>
    <th>Description</th>
    <th>Amount</th>
</tr>

<tr>
    <td>Package Subscription ('.$package.')</td>
    <td>KES '.$amount.'</td>
</tr>
</table>

<div class="total">
    Total Paid: KES '.$amount.'
</div>

<div class="footer">
    Payment received successfully.<br>
    Thank you for your business.
</div>

</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream(
    'receipt-'.$transaction_id.'.pdf',
    ['Attachment' => true]
);

exit;
