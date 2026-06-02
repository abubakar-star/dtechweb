<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

$html = "
<h1>D-LINK TEST PDF</h1>
<p>If you see this, Dompdf is working correctly.</p>
";

$dompdf->loadHtml($html);
$dompdf->render();

$dompdf->stream("invoice.pdf", [
    "Attachment" => true
]);
