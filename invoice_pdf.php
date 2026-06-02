<?php

require 'vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

// Example invoice number
$invoice = $_GET['id'] ?? 'INV-TEST';

// Your HTML invoice (you can later inject DB data here)
$html = '
<h1>D-LINK NETWORK INVOICE</h1>
<p>Invoice Number: ' . $invoice . '</p>
<hr>
<p>Thank you for your payment.</p>
';

// Load HTML
$dompdf->loadHtml($html);

// Paper setup
$dompdf->setPaper('A4', 'portrait');

// Render PDF
$dompdf->render();

// Force download
$dompdf->stream("invoice-$invoice.pdf", [
    "Attachment" => true
]);