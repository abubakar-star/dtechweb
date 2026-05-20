<?php

function detectBrowser($user_agent)
{
    if (strpos($user_agent, 'Chrome') !== false) {
        return 'Chrome';
    }

    if (strpos($user_agent, 'Firefox') !== false) {
        return 'Firefox';
    }

    if (strpos($user_agent, 'Safari') !== false) {
        return 'Safari';
    }

    if (strpos($user_agent, 'Edge') !== false) {
        return 'Edge';
    }

    return 'Unknown';
}

function detectOS($user_agent)
{
    if (strpos($user_agent, 'Windows') !== false) {
        return 'Windows';
    }

    if (strpos($user_agent, 'Android') !== false) {
        return 'Android';
    }

    if (strpos($user_agent, 'iPhone') !== false) {
        return 'iPhone';
    }

    if (strpos($user_agent, 'Mac') !== false) {
        return 'MacOS';
    }

    return 'Unknown';
}

function detectDevice($user_agent)
{
    if (strpos($user_agent, 'Mobile') !== false) {
        return 'Mobile';
    }

    if (strpos($user_agent, 'Tablet') !== false) {
        return 'Tablet';
    }

    return 'Desktop';
}

function createLog(
    $conn,
    $category,
    $action,
    $description = '',
    $level = 'info',
    $user_id = null,
    $admin_id = null
) {

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $browser = detectBrowser($user_agent);
    $os = detectOS($user_agent);
    $device = detectDevice($user_agent);

    $stmt = $conn->prepare("
        INSERT INTO system_logs (
            user_id,
            admin_id,
            log_category,
            log_action,
            description,
            log_level,
            ip_address,
            browser,
            operating_system,
            device_type
    )
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iissssssss",
        $user_id,
        $admin_id,
        $category,
        $action,
        $description,
        $level,
        $ip,
        $browser,
        $os,
        $device
    );

    $stmt->execute();
}
