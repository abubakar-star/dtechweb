<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set("Africa/Nairobi");

/* ===============================
   DATABASE CONNECTION
================================ */

$host = $_ENV['MYSQLHOST'];
$port = $_ENV['MYSQLPORT'];
$dbname = $_ENV['MYSQLDATABASE'];
$username = $_ENV['MYSQLUSER'];
$password = $_ENV['MYSQLPASSWORD'];

$conn = new mysqli(
    $host,
    $username,
    $password,
    $dbname,
    $port
);

if ($conn->connect_error) {
    die("Database connection failed");
}


/* ===============================
   SAVE AUTOMATION SETTINGS
================================ */

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['automation_id'], $_POST['is_active'])) {

        $automation_id = (int) $_POST['automation_id'];
        $is_active = (int) $_POST['is_active'];

        if ($is_active !== 0 && $is_active !== 1) {
            $is_active = 0;
        }

        $stmt = $conn->prepare("
            UPDATE sms_automations
            SET is_active = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ii",
            $is_active,
            $automation_id
        );

        if ($stmt->execute()) {

            $message = "Automation setting updated successfully.";
            $message_type = "success";

        } else {

            $message = "Failed to update automation setting.";
            $message_type = "error";
        }

        $stmt->close();
    }
}


/* ===============================
   LOAD AUTOMATIONS
================================ */

$automations = [];

$result = $conn->query("
    SELECT *
    FROM sms_automations
    ORDER BY
        CASE
            WHEN hours_before IS NOT NULL
            THEN 1
            ELSE 2
        END,
        hours_before DESC,
        id ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $automations[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>SMS Auto Reminders</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>


<body class="bg-gray-900 min-h-screen">


<div class="max-w-6xl mx-auto p-8">


    <!-- HEADER -->

    <div class="flex items-center justify-between mb-8">

        <div>

            <h1 class="text-3xl font-bold text-white">

                🔔 SMS Auto Reminders

            </h1>

            <p class="text-gray-400 mt-2">

                Automatically remind customers about expiry
                and other important account events.

            </p>

        </div>


        <a href="sms.php"
           class="bg-gray-700 hover:bg-gray-600
                  text-white px-4 py-2 rounded-lg">

            ← Back

        </a>

    </div>



    <!-- SUCCESS / ERROR MESSAGE -->

    <?php if (!empty($message)): ?>

        <div class="
            mb-6
            rounded-lg
            p-4
            <?= $message_type === 'success'
                ? 'bg-green-800 text-green-200'
                : 'bg-red-800 text-red-200'
            ?>
        ">

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>



    <!-- GLOBAL INFORMATION -->

    <div class="bg-gray-800 rounded-xl p-6 shadow mb-6">

        <div class="flex items-center gap-3">

            <div class="text-2xl">

                🤖

            </div>

            <div>

                <h2 class="text-xl font-bold text-white">

                    Automatic SMS System

                </h2>

                <p class="text-gray-400 text-sm mt-1">

                    These settings control which automatic
                    reminders will be enabled.

                </p>

            </div>

        </div>

    </div>



    <!-- EXPIRY REMINDERS -->

    <div class="bg-gray-800 rounded-xl shadow mb-6">

        <div class="p-6 border-b border-gray-700">

            <h2 class="text-xl font-bold text-white">

                ⏰ Expiry Reminders

            </h2>

            <p class="text-gray-400 text-sm mt-1">

                Notify customers before their subscription expires.

            </p>

        </div>


        <div class="divide-y divide-gray-700">

        <?php foreach ($automations as $automation): ?>

            <?php if ($automation['hours_before'] !== null): ?>

                <div class="p-6 flex items-center
                            justify-between">

                    <div>

                        <h3 class="text-white font-semibold">

                            <?= htmlspecialchars(
                                $automation['automation_name']
                            ) ?>

                        </h3>

                        <p class="text-gray-400 text-sm mt-1">

                            <?= htmlspecialchars(
                                $automation['description']
                            ) ?>

                        </p>

                    </div>


                    <form method="POST">

                        <input type="hidden"
                               name="automation_id"
                               value="<?= (int)$automation['id'] ?>">


                        <input type="hidden"
                               name="is_active"
                               value="<?= $automation['is_active'] ? 0 : 1 ?>">


                        <button type="submit"
                                class="
                                px-5
                                py-2
                                rounded-full
                                font-semibold
                                transition
                                <?= $automation['is_active']
                                    ? 'bg-green-600 hover:bg-green-700 text-white'
                                    : 'bg-gray-600 hover:bg-gray-500 text-gray-200'
                                ?>
                                ">

                            <?= $automation['is_active']
                                ? 'ON'
                                : 'OFF'
                            ?>

                        </button>

                    </form>

                </div>

            <?php endif; ?>

        <?php endforeach; ?>

        </div>

    </div>



    <!-- OTHER REMINDERS -->

    <div class="bg-gray-800 rounded-xl shadow mb-6">

        <div class="p-6 border-b border-gray-700">

            <h2 class="text-xl font-bold text-white">

                📢 Other Automatic Reminders

            </h2>

            <p class="text-gray-400 text-sm mt-1">

                Automatic messages for other account events.

            </p>

        </div>


        <div class="divide-y divide-gray-700">

        <?php foreach ($automations as $automation): ?>

            <?php if ($automation['hours_before'] === null): ?>

                <div class="p-6 flex items-center
                            justify-between">

                    <div>

                        <h3 class="text-white font-semibold">

                            <?= htmlspecialchars(
                                $automation['automation_name']
                            ) ?>

                        </h3>

                        <p class="text-gray-400 text-sm mt-1">

                            <?= htmlspecialchars(
                                $automation['description']
                            ) ?>

                        </p>

                    </div>


                    <form method="POST">

                        <input type="hidden"
                               name="automation_id"
                               value="<?= (int)$automation['id'] ?>">


                        <input type="hidden"
                               name="is_active"
                               value="<?= $automation['is_active'] ? 0 : 1 ?>">


                        <button type="submit"
                                class="
                                px-5
                                py-2
                                rounded-full
                                font-semibold
                                transition
                                <?= $automation['is_active']
                                    ? 'bg-green-600 hover:bg-green-700 text-white'
                                    : 'bg-gray-600 hover:bg-gray-500 text-gray-200'
                                ?>
                                ">

                            <?= $automation['is_active']
                                ? 'ON'
                                : 'OFF'
                            ?>

                        </button>

                    </form>

                </div>

            <?php endif; ?>

        <?php endforeach; ?>

        </div>

    </div>



    <!-- IMPORTANT NOTICE -->

    <div class="bg-gray-800 border border-yellow-700
                rounded-xl p-6">

        <h3 class="text-yellow-400 font-bold mb-2">

            ⚠ Automatic SMS Processing

        </h3>

        <p class="text-gray-400 text-sm">

            These switches currently only control the
            automation settings. They do not send SMS yet.

            The SMS processor will be added in a later
            baby step.

        </p>

    </div>


</div>

</body>
</html>