<?php

require 'vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

$html = "
<h1 style='color:green;'>D-LINK TEST PDF</h1>
<p>If you see this in PDF, Dompdf is working correctly.</p>
";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("test.pdf", ["Attachment" => true]);