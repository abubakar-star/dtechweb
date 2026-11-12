<?php

/**
 * D-LINK WEBSITE MAINTENANCE GATE
 * --------------------------------
 * Blocks normal website visitors during maintenance.
 * Allows authenticated administrators.
 * Allows essential payment callbacks.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| 1. Allow the maintenance page
|--------------------------------------------------------------------------
*/

$currentFile = basename(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)
);

if ($currentFile === 'maintenance_page.php') {
    return;
}

/*
|--------------------------------------------------------------------------
| 2. Allow essential payment callbacks
|--------------------------------------------------------------------------
*/

$allowedDuringMaintenance = [
    'onasis_callback.php',
    'onasis_package_change_callback.php',
    'onasis_sub_callback.php',
    'check_payment_status.php',
];

if (in_array($currentFile, $allowedDuringMaintenance, true)) {
    return;
}

/*
|--------------------------------------------------------------------------
| 3. Allow authenticated administrator
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['dlink_admin_authenticated']) &&
    $_SESSION['dlink_admin_authenticated'] === true
) {
    return;
}

/*
|--------------------------------------------------------------------------
| 4. Establish database connection
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {

    require_once __DIR__ . '/../db.php';

}

/*
|--------------------------------------------------------------------------
| 5. Check maintenance status
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT setting_value
    FROM site_settings
    WHERE setting_key = 'maintenance_mode'
    LIMIT 1
");

if (!$stmt) {

    error_log(
        'D-LINK Maintenance: Database query preparation failed.'
    );

    return;
}

$stmt->execute();

$result = $stmt->get_result();

$row = $result->fetch_assoc();

$stmt->close();

$maintenanceEnabled = (
    isset($row['setting_value']) &&
    $row['setting_value'] === '1'
);

/*
|--------------------------------------------------------------------------
| 6. Display maintenance page
|--------------------------------------------------------------------------
*/

if ($maintenanceEnabled) {

    http_response_code(503);

    header('Retry-After: 1800');

    require __DIR__ . '/../maintenance_page.php';

    exit();

}

?>
