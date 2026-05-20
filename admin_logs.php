<?php
include 'db.php';

$result = mysqli_query($conn, "
    SELECT * FROM system_logs
    ORDER BY created_at DESC
    LIMIT 200
");
?>

<!DOCTYPE html>
<html>
<head>

    <title>DTECH Logs</title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 p-6">

<div class="bg-white rounded-2xl shadow-xl overflow-hidden">

    <div class="p-5 border-b">

        <h1 class="text-2xl font-bold text-gray-800">
            DTECH System Logs
        </h1>

        <p class="text-gray-500 mt-1">
            Monitor activity, security and payments.
        </p>

    </div>

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">

                <tr>

                    <th class="p-4 text-left">Time</th>
                    <th class="p-4 text-left">Category</th>
                    <th class="p-4 text-left">Action</th>
                    <th class="p-4 text-left">Level</th>
                    <th class="p-4 text-left">IP</th>
                    <th class="p-4 text-left">Browser</th>
                    <th class="p-4 text-left">OS</th>
                    <th class="p-4 text-left">Device</th>

                </tr>

            </thead>

            <tbody>

            <?php while($log = mysqli_fetch_assoc($result)): ?>

                <?php

                $colors = [
                    'success' => 'green',
                    'warning' => 'yellow',
                    'error' => 'red',
                    'critical' => 'purple',
                    'info' => 'blue'
                ];

                $color = $colors[$log['log_level']] ?? 'gray';

                ?>
</html>