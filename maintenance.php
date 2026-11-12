<?php

/**
 * D-LINK NETWORK
 * Maintenance Mode Administration
 *
 * This page is protected by the existing administrator authentication.
 * It creates the required site_settings table automatically if it
 * does not already exist.
 */

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/db.php';

/*
|--------------------------------------------------------------------------
| CREATE SETTINGS TABLE IF IT DOES NOT EXIST
|--------------------------------------------------------------------------
*/

$createTableSql = "
    CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if (!$conn->query($createTableSql)) {

    error_log(
        'D-LINK Maintenance: Unable to create site_settings table: ' .
        $conn->error
    );

    http_response_code(500);

    exit('Unable to initialize maintenance settings.');
}


/*
|--------------------------------------------------------------------------
| DEFAULT SETTINGS
|--------------------------------------------------------------------------
*/

$defaultMessage =
    'We are currently carrying out scheduled website maintenance to improve our services.';

$notice = '';
$error = '';


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['maintenance_csrf'])) {

    $_SESSION['maintenance_csrf'] =
        bin2hex(random_bytes(32));

}


/*
|--------------------------------------------------------------------------
| SAVE SETTINGS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals(
            $_SESSION['maintenance_csrf'],
            $submittedToken
        )
    ) {

        $error =
            'Invalid request. Please refresh the page and try again.';

    } else {

        $maintenanceEnabled =
            isset($_POST['maintenance_enabled'])
            ? '1'
            : '0';

        $maintenanceMessage =
            trim(
                (string)(
                    $_POST['maintenance_message']
                    ?? ''
                )
            );

        if ($maintenanceMessage === '') {

            $maintenanceMessage = $defaultMessage;

        }

        /*
         * Limit the message to a safe length.
         * strlen is used for maximum compatibility with the
         * current production PHP environment.
         */

        if (strlen($maintenanceMessage) > 1000) {

            $error =
                'The maintenance message must not exceed 1000 characters.';

        } else {

            /*
             * Save maintenance mode.
             */

            $modeKey = 'maintenance_mode';

            $stmt = $conn->prepare("
                INSERT INTO site_settings
                    (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value)
            ");

            if (!$stmt) {

                error_log(
                    'D-LINK Maintenance: Could not prepare maintenance mode query: ' .
                    $conn->error
                );

                $error =
                    'Unable to prepare maintenance settings.';

            } else {

                $stmt->bind_param(
                    'ss',
                    $modeKey,
                    $maintenanceEnabled
                );

                $modeSaved = $stmt->execute();

                if (!$modeSaved) {

                    error_log(
                        'D-LINK Maintenance: Could not save maintenance mode: ' .
                        $stmt->error
                    );

                    $error =
                        'Unable to save maintenance mode.';

                }

                $stmt->close();

            }


            /*
             * Save maintenance message only if the mode
             * was successfully saved.
             */

            if ($error === '') {

                $messageKey = 'maintenance_message';

                $stmt = $conn->prepare("
                    INSERT INTO site_settings
                        (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value)
                ");

                if (!$stmt) {

                    error_log(
                        'D-LINK Maintenance: Could not prepare maintenance message query: ' .
                        $conn->error
                    );

                    $error =
                        'Maintenance mode was saved, but the message could not be saved.';

                } else {

                    $stmt->bind_param(
                        'ss',
                        $messageKey,
                        $maintenanceMessage
                    );

                    $messageSaved = $stmt->execute();

                    if (!$messageSaved) {

                        error_log(
                            'D-LINK Maintenance: Could not save maintenance message: ' .
                            $stmt->error
                        );

                        $error =
                            'Maintenance mode was saved, but the message could not be saved.';

                    }

                    $stmt->close();

                }

            }


            /*
             * Show success message and create a new CSRF token.
             */

            if ($error === '') {

                if ($maintenanceEnabled === '1') {

                    $notice =
                        'Maintenance mode is now ON. Customers will see the maintenance page on protected pages.';

                } else {

                    $notice =
                        'Maintenance mode is now OFF. The website is available normally.';

                }

                $_SESSION['maintenance_csrf'] =
                    bin2hex(random_bytes(32));

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| READ CURRENT SETTINGS
|--------------------------------------------------------------------------
*/

$settings = [

    'maintenance_mode' => '0',

    'maintenance_message' => $defaultMessage

];

$result = $conn->query("
    SELECT setting_key, setting_value
    FROM site_settings
    WHERE setting_key IN
        ('maintenance_mode', 'maintenance_message')
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if (
            isset($row['setting_key']) &&
            array_key_exists(
                $row['setting_key'],
                $settings
            )
        ) {

            $settings[$row['setting_key']] =
                $row['setting_value'];

        }

    }

    $result->free();

}

$isEnabled =
    $settings['maintenance_mode'] === '1';

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<title>Maintenance Mode | D-LINK Admin</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-900 min-h-screen text-white p-5">

<div class="w-full max-w-2xl mx-auto">

    <!-- PAGE HEADER -->

    <div class="flex items-center justify-between gap-4 mb-6">

        <div>

            <h1 class="text-2xl font-bold">
                🛠️ Maintenance Mode
            </h1>

            <p class="text-sm text-gray-400 mt-1">
                Control whether customers can access the website.
            </p>

        </div>

        <a
            href="tosha.php"
            class="bg-gray-700 hover:bg-gray-600 transition px-4 py-2 rounded-lg text-sm font-semibold"
        >
            ← Admin Panel
        </a>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if ($notice): ?>

        <div class="mb-5 rounded-lg border border-green-500/40 bg-green-500/10 p-4 text-green-200">

            <?= htmlspecialchars(
                $notice,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ERROR MESSAGE -->

    <?php if ($error): ?>

        <div class="mb-5 rounded-lg border border-red-500/40 bg-red-500/10 p-4 text-red-200">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- MAIN PANEL -->

    <div class="bg-gray-800 rounded-xl shadow-xl p-6 border border-gray-700">


        <!-- CURRENT STATUS -->

        <div
            class="flex items-center justify-between gap-4 mb-6 p-4 rounded-lg
            <?= $isEnabled
                ? 'bg-amber-500/10 border border-amber-500/30'
                : 'bg-green-500/10 border border-green-500/30'
            ?>"
        >

            <div>

                <p class="font-bold">

                    Current status:

                    <?= $isEnabled
                        ? 'MAINTENANCE ON'
                        : 'WEBSITE LIVE'
                    ?>

                </p>

                <p class="text-sm text-gray-300 mt-1">

                    Administrators can still access protected admin pages.

                </p>

            </div>

            <span
                class="px-3 py-1 rounded-full text-xs font-bold
                <?= $isEnabled
                    ? 'bg-amber-500 text-gray-950'
                    : 'bg-green-600 text-white'
                ?>"
            >

                <?= $isEnabled ? 'ON' : 'OFF' ?>

            </span>

        </div>


        <!-- SETTINGS FORM -->

        <form
            method="POST"
            action="maintenance.php"
            class="space-y-6"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['maintenance_csrf'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <!-- ENABLE / DISABLE -->

            <label
                class="flex items-center justify-between gap-4 cursor-pointer p-4 bg-gray-700/60 rounded-lg"
            >

                <div>

                    <span class="font-semibold">
                        Enable maintenance mode
                    </span>

                    <p class="text-sm text-gray-400 mt-1">

                        When enabled, customers visiting protected pages
                        will see the maintenance page.

                    </p>

                </div>

                <input
                    type="checkbox"
                    name="maintenance_enabled"
                    value="1"
                    <?= $isEnabled ? 'checked' : '' ?>
                    class="w-5 h-5"
                >

            </label>


            <!-- CUSTOMER MESSAGE -->

            <div>

                <label
                    for="maintenance_message"
                    class="block text-sm font-semibold mb-2"
                >

                    Customer message

                </label>

                <textarea
                    id="maintenance_message"
                    name="maintenance_message"
                    rows="5"
                    maxlength="1000"
                    class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 outline-none focus:border-blue-500"
                ><?= htmlspecialchars(
                    $settings['maintenance_message'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

                <p class="text-xs text-gray-500 mt-2">

                    This message is displayed to customers on the
                    maintenance page.

                </p>

            </div>


            <!-- SAVE BUTTON -->

            <button
                type="submit"
                class="w-full bg-orange-600 hover:bg-orange-700 transition py-3 rounded-lg font-bold"
            >

                Save Maintenance Settings

            </button>

        </form>

    </div>

</div>

</body>

</html>