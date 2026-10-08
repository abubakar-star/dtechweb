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

    $automation_id = isset($_POST['automation_id'])
        ? (int) $_POST['automation_id']
        : 0;

    $is_active = isset($_POST['is_active'])
        ? (int) $_POST['is_active']
        : 0;

    $template_id = isset($_POST['template_id'])
        ? (int) $_POST['template_id']
        : 0;


    /* ---------------------------
       Validate active value
    ---------------------------- */

    if ($is_active !== 0 && $is_active !== 1) {
        $is_active = 0;
    }


    /* ---------------------------
       Convert empty template
       to NULL
    ---------------------------- */

    if ($template_id <= 0) {
        $template_id = null;
    }


    /* ---------------------------
       Update
    ---------------------------- */

    $stmt = $conn->prepare("
        UPDATE sms_automations
        SET
            is_active = ?,
            template_id = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "iii",
        $is_active,
        $template_id,
        $automation_id
    );


    if ($stmt->execute()) {

        $message = "Automation settings saved successfully.";
        $message_type = "success";

    } else {

        $message = "Failed to save automation settings.";
        $message_type = "error";
    }

    $stmt->close();
}


/* ===============================
   LOAD SMS TEMPLATES
================================ */

$templates = [];

$result = $conn->query("
    SELECT
        id,
        template_name,
        message
    FROM sms_templates
    ORDER BY template_name ASC
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $templates[] = $row;
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
            WHEN hours_before IS NOT NULL THEN 1
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


    <!-- =========================
         HEADER
    ========================== -->

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



    <!-- =========================
         MESSAGE
    ========================== -->

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



    <!-- =========================
         INFORMATION
    ========================== -->

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

                    Choose which reminders are active and
                    which SMS template should be used.

                </p>

            </div>

        </div>

    </div>



    <!-- =========================
         EXPIRY REMINDERS
    ========================== -->

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


                <div class="p-6">

                    <form method="POST">


                        <!-- Automation ID -->

                        <input
                            type="hidden"
                            name="automation_id"
                            value="<?= (int)$automation['id'] ?>"
                        >


                        <div class="flex items-start
                                    justify-between
                                    gap-6">


                            <!-- LEFT SIDE -->

                            <div class="flex-1">

                                <h3 class="text-white
                                           font-semibold
                                           text-lg">

                                    <?= htmlspecialchars(
                                        $automation['automation_name']
                                    ) ?>

                                </h3>


                                <p class="text-gray-400
                                          text-sm
                                          mt-1">

                                    <?= htmlspecialchars(
                                        $automation['description']
                                    ) ?>

                                </p>


                                <!-- TEMPLATE -->

                                <div class="mt-5">

                                    <label
                                        class="block
                                               text-gray-300
                                               text-sm
                                               mb-2">

                                        SMS Template

                                    </label>


                                    <select
                                        name="template_id"
                                        class="w-full
                                               max-w-xl
                                               bg-gray-700
                                               text-white
                                               rounded-lg
                                               p-3
                                               border
                                               border-gray-600
                                               focus:outline-none
                                               focus:ring-2
                                               focus:ring-purple-500"
                                    >

                                        <option value="0">

                                            -- Select SMS Template --

                                        </option>


                                        <?php foreach ($templates as $template): ?>

                                            <option
                                                value="<?= (int)$template['id'] ?>"
                                                <?= (
                                                    (int)$automation['template_id']
                                                    ===
                                                    (int)$template['id']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $template['template_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                            </div>


                            <!-- RIGHT SIDE -->

                            <div class="flex flex-col
                                        items-end
                                        gap-4">


                                <!-- STATUS -->

                                <button
                                    type="button"
                                    onclick="toggleStatus(
                                        <?= (int)$automation['id'] ?>
                                    )"
                                    class="
                                    px-5
                                    py-2
                                    rounded-full
                                    font-semibold
                                    <?= $automation['is_active']
                                        ? 'bg-green-600 text-white'
                                        : 'bg-gray-600 text-gray-200'
                                    ?>
                                    "
                                    id="statusButton<?= (int)$automation['id'] ?>"
                                >

                                    <?= $automation['is_active']
                                        ? 'ON'
                                        : 'OFF'
                                    ?>

                                </button>


                                <input
                                    type="hidden"
                                    name="is_active"
                                    id="statusInput<?= (int)$automation['id'] ?>"
                                    value="<?= (int)$automation['is_active'] ?>"
                                >


                                <!-- SAVE -->

                                <button
                                    type="submit"
                                    class="
                                    bg-purple-700
                                    hover:bg-purple-800
                                    text-white
                                    px-5
                                    py-2
                                    rounded-lg
                                    font-semibold
                                    "
                                >

                                    Save

                                </button>


                            </div>

                        </div>

                    </form>

                </div>


            <?php endif; ?>


        <?php endforeach; ?>


        </div>

    </div>



    <!-- =========================
         OTHER REMINDERS
    ========================== -->

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


                <div class="p-6">

                    <form method="POST">


                        <input
                            type="hidden"
                            name="automation_id"
                            value="<?= (int)$automation['id'] ?>"
                        >


                        <div class="flex items-start
                                    justify-between
                                    gap-6">


                            <!-- LEFT -->

                            <div class="flex-1">

                                <h3 class="text-white
                                           font-semibold
                                           text-lg">

                                    <?= htmlspecialchars(
                                        $automation['automation_name']
                                    ) ?>

                                </h3>


                                <p class="text-gray-400
                                          text-sm
                                          mt-1">

                                    <?= htmlspecialchars(
                                        $automation['description']
                                    ) ?>

                                </p>


                                <!-- TEMPLATE -->

                                <div class="mt-5">

                                    <label
                                        class="block
                                               text-gray-300
                                               text-sm
                                               mb-2">

                                        SMS Template

                                    </label>


                                    <select
                                        name="template_id"
                                        class="w-full
                                               max-w-xl
                                               bg-gray-700
                                               text-white
                                               rounded-lg
                                               p-3
                                               border
                                               border-gray-600
                                               focus:outline-none
                                               focus:ring-2
                                               focus:ring-purple-500"
                                    >

                                        <option value="0">

                                            -- Select SMS Template --

                                        </option>


                                        <?php foreach ($templates as $template): ?>

                                            <option
                                                value="<?= (int)$template['id'] ?>"
                                                <?= (
                                                    (int)$automation['template_id']
                                                    ===
                                                    (int)$template['id']
                                                )
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    $template['template_name']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                            </div>


                            <!-- RIGHT -->

                            <div class="flex flex-col
                                        items-end
                                        gap-4">


                                <button
                                    type="button"
                                    onclick="toggleStatus(
                                        <?= (int)$automation['id'] ?>
                                    )"
                                    class="
                                    px-5
                                    py-2
                                    rounded-full
                                    font-semibold
                                    <?= $automation['is_active']
                                        ? 'bg-green-600 text-white'
                                        : 'bg-gray-600 text-gray-200'
                                    ?>
                                    "
                                    id="statusButton<?= (int)$automation['id'] ?>"
                                >

                                    <?= $automation['is_active']
                                        ? 'ON'
                                        : 'OFF'
                                    ?>

                                </button>


                                <input
                                    type="hidden"
                                    name="is_active"
                                    id="statusInput<?= (int)$automation['id'] ?>"
                                    value="<?= (int)$automation['is_active'] ?>"
                                >


                                <button
                                    type="submit"
                                    class="
                                    bg-purple-700
                                    hover:bg-purple-800
                                    text-white
                                    px-5
                                    py-2
                                    rounded-lg
                                    font-semibold
                                    "
                                >

                                    Save

                                </button>


                            </div>


                        </div>

                    </form>

                </div>


            <?php endif; ?>


        <?php endforeach; ?>


        </div>

    </div>



    <!-- =========================
         IMPORTANT NOTICE
    ========================== -->

    <div class="bg-gray-800
                border
                border-yellow-700
                rounded-xl
                p-6">


        <h3 class="text-yellow-400
                   font-bold
                   mb-2">

            ⚠ Automatic SMS Processing

        </h3>


        <p class="text-gray-400 text-sm">

            The settings above only determine which
            automation rules are enabled and which
            templates they will use.

            SMS sending will be connected in a later step.

        </p>


    </div>


</div>



<script>

/* ===============================
   TOGGLE ON / OFF
================================ */

function toggleStatus(id)
{

    const input =
        document.getElementById(
            'statusInput' + id
        );

    const button =
        document.getElementById(
            'statusButton' + id
        );


    if (input.value === '1') {

        input.value = '0';

        button.textContent = 'OFF';

        button.classList.remove(
            'bg-green-600',
            'text-white'
        );

        button.classList.add(
            'bg-gray-600',
            'text-gray-200'
        );

    } else {

        input.value = '1';

        button.textContent = 'ON';

        button.classList.remove(
            'bg-gray-600',
            'text-gray-200'
        );

        button.classList.add(
            'bg-green-600',
            'text-white'
        );

    }

}

</script>


</body>

</html>
