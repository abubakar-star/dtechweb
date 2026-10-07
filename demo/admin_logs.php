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
            DTECH System Logs </h1>

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

                <tr class="border-b hover:bg-gray-50 transition">

                    <td class="p-4 text-sm text-gray-500">
                        <?= $log['created_at'] ?>
                    </td>

                    <td class="p-4">

                        <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">
                            <?= strtoupper($log['log_category']) ?>
                        </span>

                    </td>

                    <td class="p-4">

                        <div class="font-semibold text-gray-800">
                            <?= htmlspecialchars($log['log_action']) ?>
                        </div>

                        <div class="text-sm text-gray-500">
                            <?= htmlspecialchars($log['description']) ?>
                        </div>

                    </td>

                    <td class="p-4">

                        <span class="bg-<?= $color ?>-100 text-<?= $color ?>-700 px-3 py-1 rounded-full text-xs font-bold">
                            <?= strtoupper($log['log_level']) ?>
                        </span>

                    </td>

                    <td class="p-4 text-sm">
                        <?= $log['ip_address'] ?>
                    </td>

                    <td class="p-4 text-sm">
                        <?= $log['browser'] ?>
                    </td>

                    <td class="p-4 text-sm">
                        <?= $log['operating_system'] ?>
                    </td>

                    <td class="p-4 text-sm">
                        <?= $log['device_type'] ?>
                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
