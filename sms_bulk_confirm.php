<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sms_bulk.php");
    exit();
}

$campaignTitle = trim($_POST['campaign_title'] ?? '');
$recipientGroup = trim($_POST['recipient_group'] ?? '');
$packageId = trim($_POST['package_id'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($campaignTitle === '' || $recipientGroup === '' || $message === '') {
    header("Location: sms_bulk.php");
    exit();
}

/* ===============================
   FRIENDLY RECIPIENT GROUP NAME
================================ */

$groupNames = [
    'active'    => 'All Active Customers',
    'expired'   => 'Expired Customers',
    'expiring3' => 'Expiring Within 3 Days',
    'package'   => 'Specific Package'
];

$groupDisplay = $groupNames[$recipientGroup] ?? $recipientGroup;
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Confirm Bulk SMS</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 min-h-screen">

<div class="max-w-4xl mx-auto p-8">

    <div class="bg-gray-800 rounded-xl p-8">

        <h1 class="text-3xl font-bold text-white mb-6">
            Confirm Bulk SMS
        </h1>

        <div class="space-y-4 text-white">

            <div>
                <strong>Campaign Title:</strong>
                <?= htmlspecialchars($campaignTitle) ?>
            </div>

            <div>
                <strong>Recipient Group:</strong>
               <?= htmlspecialchars($groupDisplay) ?>
            </div>

            <div>
                <strong>Message:</strong>

                <div class="mt-2 bg-gray-700 p-4 rounded whitespace-pre-wrap">
                    <?= htmlspecialchars($message) ?>
                </div>

            </div>

        </div>

        <div class="mt-8 flex justify-between">

            <a
                href="sms_bulk.php"
                class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded">

                ← Back

            </a>

            <button
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded">

                Send SMS

            </button>

        </div>

    </div>

</div>

</body>

</html>
