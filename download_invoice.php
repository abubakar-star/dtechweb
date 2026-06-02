<?php

if (!isset($_GET['id'])) {
    exit("Missing invoice ID");
}

$invoice = $_GET['id'];

// For now just create a simple text-based PDF response test
header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=invoice-$invoice.pdf");

// TEMP TEST CONTENT (we will upgrade later to real PDF)
echo "INVOICE: " . $invoice;

exit;