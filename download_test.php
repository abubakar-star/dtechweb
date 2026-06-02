<?php

header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=test.pdf");

readfile("test.pdf");

exit;