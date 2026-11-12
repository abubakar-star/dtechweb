<?php

/**
 * D-LINK WEBSITE MAINTENANCE GATE
 *
 * Checks whether website maintenance is enabled.
 * Blocks ordinary visitors while allowing:
 * - The maintenance page
 * - Payment callbacks
 * - Payment status checks
 * - Authenticated administrators
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Files that must remain accessible during maintenance.
 */
$maintenanceAllowedFiles = [

    'maintenance_page.php',

    // Payment callbacks
    'onasis_callback.php',
    'onasis_package_change_callback.php',
    'onasis_sub_callback.php',
    'centipid_webhook.php',

    // Payment status checks
    'check_payment_status.php',
    'check_payment_sub_status.php',

];

/**
 * Identify the currently requested PHP file.
 */
$currentFile = basename(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)
);

/**
 * Allow essential payment operations.
 */
if (in_array($currentFile, $maintenanceAllowedFiles, true)) {
    return;
}

/**
 * Allow administrators authenticated by the
 * maintenance administration system.
 */
if (
    isset($_SESSION['dlink_admin_authenticated']) &&
    $_SESSION['dlink_admin_authenticated'] === true
) {
    return;
}

/**
 * Check maintenance status.
 */
if (!isset($conn) || !($conn instanceof mysqli)) {

    error_log(
        'D-LINK Maintenance Gate: Database connection unavailable.'
    );

    return;
}

$stmt = $conn->prepare("
    SELECT setting_value
    FROM site_settings
    WHERE setting_key = 'maintenance_mode'
    LIMIT 1
");

if (!$stmt) {

    error_log(
        'D-LINK Maintenance Gate: Could not prepare database query.'
    );

    return;
}

$stmt->execute();

$result = $stmt->get_result();

$maintenance = $result->fetch_assoc();

$stmt->close();

/**
 * Maintenance is active when value is 1.
 */
$maintenanceEnabled = (
    isset($maintenance['setting_value']) &&
    $maintenance['setting_value'] === '1'
);

/**
 * Redirect visitors to the maintenance page.
 */
if ($maintenanceEnabled) {

    http_response_code(503);

    header('Location: maintenance_page.php');

    exit();
}

?>
