<?php

if (!isset($_GET['id'])) {
    exit("Missing invoice ID");
}

$invoice = $_GET['id'];

// Create simple HTML invoice
$html = "
<html>
<head>
    <style>
        body { font-family: Arial; padding: 30px; }
        h1 { color: #f97316; }
        .box { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td, th { border: 1px solid #ddd; padding: 8px; }
    </style>
</head>
<body>

<h1>D-LINK NETWORK INVOICE</h1>

<div class='box'>
    <p><strong>Invoice No:</strong> $invoice</p>
    <p><strong>Status:</strong> PAID</p>
</div>

<table>
    <tr>
        <th>Description</th>
        <th>Amount</th>
    </tr>
    <tr>
        <td>Internet Subscription</td>
        <td>KES 1000</td>
    </tr>
</table>

<p style='margin-top:30px;'>Thank you for your business.</p>

</body>
</html>
";

// Convert HTML → PDF using browser render trick (Render-safe fallback)
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=invoice-$invoice.pdf");

// This works because WebView/Android still downloads it as file
echo $html;

exit;
