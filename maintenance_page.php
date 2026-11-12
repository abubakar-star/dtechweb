<?php
http_response_code(503);

$siteName = 'D-LINK Network';

$message = 'We are currently carrying out scheduled website maintenance to improve our services.';

if (isset($conn) && $conn instanceof mysqli) {

    $msgResult = $conn->query("
        SELECT setting_value
        FROM site_settings
        WHERE setting_key = 'maintenance_message'
        LIMIT 1
    ");

    if ($msgResult && ($msgRow = $msgResult->fetch_assoc())) {
        $message = $msgRow['setting_value'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<meta name="robots" content="noindex,nofollow">

<title>Under Maintenance | D-LINK Network</title>

<script src="https://cdn.tailwindcss.com"></script>

<style>

body {
    background: radial-gradient(
        circle at top,
        #183b68 0,
        #0b1424 55%,
        #070d18 100%
    );
}

.orbit {
    animation: spin 12s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

</style>

</head>

<body class="min-h-screen text-white flex items-center justify-center p-5">

<main class="w-full max-w-xl text-center">

    <!-- Maintenance Animation -->

    <div class="mx-auto mb-8 relative h-28 w-28 flex items-center justify-center">

        <div class="orbit absolute inset-0 rounded-full border-2 border-dashed border-blue-400/60"></div>

        <div class="h-20 w-20 rounded-3xl bg-blue-600 shadow-2xl shadow-blue-500/30 flex items-center justify-center text-4xl">

            ⚙️

        </div>

    </div>


    <!-- Maintenance Status -->

    <div class="inline-flex items-center gap-2 rounded-full border border-amber-300/30 bg-amber-300/10 px-4 py-2 text-xs font-semibold tracking-widest text-amber-200 mb-5">

        <span class="h-2 w-2 rounded-full bg-amber-300 animate-pulse"></span>

        WEBSITE MAINTENANCE

    </div>


    <!-- Main Heading -->

    <h1 class="text-4xl sm:text-5xl font-black tracking-tight mb-5">

        We'll be back soon.

    </h1>


    <!-- Custom Maintenance Message -->

    <p class="text-slate-300 text-lg leading-relaxed mb-8">

        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>

    </p>


    <!-- Information Box -->

    <div class="rounded-2xl border border-white/10 bg-white/5 p-5 text-left">

        <p class="font-semibold mb-2">

            What's happening?

        </p>

        <p class="text-sm text-slate-300">

            Our team is performing system updates and improvements.

            The website will be available again as soon as maintenance is complete.

        </p>

    </div>


    <!-- Footer -->

    <p class="text-slate-400 text-sm mt-8">

        Thank you for your patience.

    </p>

    <p class="text-slate-500 text-xs mt-3">

        © <?= date('Y') ?> D-LINK Network. All rights reserved.

    </p>

</main>

</body>

</html>
