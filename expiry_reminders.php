<?php
/**
 * D-LINK NETWORK - Subscription expiry reminders
 *
 * Run this script from a scheduler (recommended: every 5 minutes).
 * It sends each reminder once per subscription cycle to:
 *   1. The client
 *   2. The configured admin phone
 *
 * Milestones:
 *   - 5 days remaining
 *   - 2 days remaining
 *   - 4 hours remaining
 *   - Expiry
 *
 * The expiry_at column makes a new set of reminders possible after renewal.
 */

date_default_timezone_set('Africa/Nairobi');

// This job must run from a scheduler/CLI, not as a public web endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/logger.php';

const REMINDER_WINDOW_MINUTES = 10;

/**
 * Convert Kenyan/local phone formats to 254XXXXXXXXX.
 */
function formatKenyanPhone(?string $phone): string
{
    $phone = trim((string)$phone);

    if ($phone === '') {
        return '';
    }

    if (strpos($phone, '+254') === 0) {
        return '254' . substr($phone, 4);
    }

    if (strpos($phone, '254') === 0) {
        return $phone;
    }

    if ($phone[0] === '0') {
        return '254' . substr($phone, 1);
    }

    return $phone;
}

/**
 * Send one SMS through the configured TalkSasa account.
 */
function sendExpirySms(string $recipient, string $message, array $settings): array
{
    $recipient = formatKenyanPhone($recipient);

    if ($recipient === '') {
        return [
            'success' => false,
            'error' => 'Recipient phone number is empty'
        ];
    }

    $payload = json_encode([
        'recipient' => $recipient,
        'sender_id' => $settings['sender_id'],
        'type'      => 'plain',
        'message'   => $message
    ]);

    $ch = curl_init('https://bulksms.talksasa.com/api/v3/sms/send');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $settings['api_token'],
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($curlError !== '') {
        return [
            'success' => false,
            'error'   => $curlError,
            'response'=> $response
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'error'   => 'TalkSasa HTTP ' . $httpCode,
            'response'=> $response
        ];
    }

    // TalkSasa normally returns JSON. If it contains an explicit failure,
    // do not mark the notification as sent.
    $decoded = json_decode((string)$response, true);

    if (is_array($decoded)) {
        if (isset($decoded['success']) && $decoded['success'] === false) {
            return [
                'success'  => false,
                'error'    => $decoded['message'] ?? 'TalkSasa rejected the SMS',
                'response' => $response
            ];
        }

        if (isset($decoded['status'])) {
            $status = strtolower((string)$decoded['status']);

            if (in_array($status, ['failed', 'error', 'rejected'], true)) {
                return [
                    'success'  => false,
                    'error'    => $decoded['message'] ?? 'TalkSasa rejected the SMS',
                    'response' => $response
                ];
            }
        }
    }

    return [
        'success'  => true,
        'response' => $response
    ];
}

/**
 * Return true only if this exact user/subscription/milestone/recipient
 * has already been successfully sent.
 */
function notificationAlreadySent(
    mysqli $conn,
    int $userId,
    string $expiryAt,
    string $milestone,
    string $recipientType
): bool {
    $stmt = $conn->prepare("
        SELECT id
        FROM expiry_notifications
        WHERE user_id = ?
          AND expiry_at = ?
          AND milestone = ?
          AND recipient_type = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        'isss',
        $userId,
        $expiryAt,
        $milestone,
        $recipientType
    );

    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

/**
 * Record a successfully sent notification.
 */
function recordNotification(
    mysqli $conn,
    int $userId,
    string $expiryAt,
    string $milestone,
    string $recipientType
): bool {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO expiry_notifications
        (user_id, expiry_at, milestone, recipient_type, sent_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        'isss',
        $userId,
        $expiryAt,
        $milestone,
        $recipientType
    );

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/* =========================================================
   1. MAKE SURE THE NOTIFICATION TABLE EXISTS
   ========================================================= */

$createTable = $conn->query("
    CREATE TABLE IF NOT EXISTS expiry_notifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        expiry_at DATETIME NOT NULL,
        milestone VARCHAR(20) NOT NULL,
        recipient_type ENUM('client','admin') NOT NULL,
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_expiry_notification
            (user_id, expiry_at, milestone, recipient_type),
        KEY idx_expiry_user (user_id),
        KEY idx_expiry_at (expiry_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (!$createTable) {
    createLog(
        $conn,
        'sms',
        'expiry_notification_table_failed',
        'Could not create expiry_notifications table: ' . $conn->error,
        'critical'
    );

    http_response_code(500);
    exit('Expiry notification table could not be created.');
}

/* =========================================================
   2. LOAD SMS SETTINGS
   ========================================================= */

$settingsResult = $conn->query("
    SELECT api_token, sender_id
    FROM sms_settings
    WHERE is_active = 1
    LIMIT 1
");

if (!$settingsResult || $settingsResult->num_rows === 0) {
    createLog(
        $conn,
        'sms',
        'expiry_sms_disabled',
        'Expiry reminder job could not run because SMS settings are disabled or missing.',
        'warning'
    );

    exit('SMS settings are disabled or missing.');
}

$smsSettings = $settingsResult->fetch_assoc();

if (empty($smsSettings['api_token']) || empty($smsSettings['sender_id'])) {
    createLog(
        $conn,
        'sms',
        'expiry_sms_configuration_invalid',
        'Expiry reminder job found incomplete SMS settings.',
        'error'
    );

    exit('SMS configuration is incomplete.');
}

/* =========================================================
   3. LOAD ADMIN PHONE
   ========================================================= */

$adminPhone = '';

$adminResult = $conn->query("
    SELECT phone_number
    FROM admin_contacts
    WHERE phone_number IS NOT NULL
      AND phone_number <> ''
    LIMIT 1
");

if ($adminResult && $adminResult->num_rows > 0) {
    $adminRow = $adminResult->fetch_assoc();
    $adminPhone = formatKenyanPhone($adminRow['phone_number']);
}

/* =========================================================
   4. DETERMINE WHICH MILESTONE IS CURRENTLY DUE
   ========================================================= */

$milestones = [
    [
        'key' => '5_days',
        'seconds' => 5 * 24 * 60 * 60,
        'label' => '5 days'
    ],
    [
        'key' => '2_days',
        'seconds' => 2 * 24 * 60 * 60,
        'label' => '2 days'
    ],
    [
        'key' => '4_hours',
        'seconds' => 4 * 60 * 60,
        'label' => '4 hours'
    ],
];

/*
 * The scheduler should run every 5 minutes.
 * A 10-minute window gives it enough tolerance if a run starts a
 * few minutes late.
 */
$now = time();
$windowSeconds = REMINDER_WINDOW_MINUTES * 60;

$totalSent = 0;
$totalFailed = 0;

/* =========================================================
   5. SEND 5-DAY / 2-DAY / 4-HOUR REMINDERS
   ========================================================= */

foreach ($milestones as $milestone) {

    $targetTimestamp = $now + $milestone['seconds'];

    $windowStart = date(
        'Y-m-d H:i:s',
        $targetTimestamp - $windowSeconds
    );

    $windowEnd = date(
        'Y-m-d H:i:s',
        $targetTimestamp + $windowSeconds
    );

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.username,
            u.first_name,
            u.phone_number,
            u.Expiry,
            u.status,
            p.package_name,
            p.speed,
            p.price
        FROM users u
        LEFT JOIN packages p ON p.id = u.package_id
        WHERE u.status = 'active'
          AND u.Expiry BETWEEN ? AND ?
    ");

    $stmt->bind_param('ss', $windowStart, $windowEnd);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($user = $result->fetch_assoc()) {

        $userId = (int)$user['id'];
        $expiryAt = $user['Expiry'];
        $clientPhone = formatKenyanPhone($user['phone_number']);

        $packageName = $user['package_name'] ?? 'your package';
        $speed = $user['speed'] ?? '';
        $price = isset($user['price'])
            ? 'KES ' . number_format((float)$user['price'], 0)
            : '';

        $clientName = trim($user['first_name'] ?? '');
        if ($clientName === '') {
            $clientName = $user['username'];
        }

        $expiryDisplay = date(
            'd M Y, H:i',
            strtotime($expiryAt)
        );

        $clientMessage =
            "Hello {$clientName},\n" .
            "D-LINK NETWORK subscription reminder.\n" .
            "Your {$packageName}" .
            ($speed !== '' ? " ({$speed})" : '') .
            " subscription expires in {$milestone['label']}.\n" .
            "Expiry: {$expiryDisplay}.\n" .
            "Please renew before expiry to avoid service interruption.";

        if ($clientPhone !== '' &&
            !notificationAlreadySent(
                $conn,
                $userId,
                $expiryAt,
                $milestone['key'],
                'client'
            )
        ) {

            $sms = sendExpirySms(
                $clientPhone,
                $clientMessage,
                $smsSettings
            );

            if ($sms['success']) {

                recordNotification(
                    $conn,
                    $userId,
                    $expiryAt,
                    $milestone['key'],
                    'client'
                );

                $totalSent++;

                createLog(
                    $conn,
                    'sms',
                    'expiry_reminder_sent',
                    "Sent {$milestone['label']} expiry reminder to client {$user['username']}.",
                    'info',
                    $userId
                );

            } else {

                $totalFailed++;

                createLog(
                    $conn,
                    'sms',
                    'expiry_reminder_failed',
                    "Failed {$milestone['label']} reminder for client {$user['username']}: " .
                    ($sms['error'] ?? 'Unknown SMS error'),
                    'error',
                    $userId
                );
            }
        }

        if ($adminPhone !== '' &&
            !notificationAlreadySent(
                $conn,
                $userId,
                $expiryAt,
                $milestone['key'],
                'admin'
            )
        ) {

            $adminMessage =
                "D-LINK NETWORK EXPIRY ALERT\n" .
                "Client: {$user['username']}\n" .
                "Phone: {$clientPhone}\n" .
                "Package: {$packageName}" .
                ($speed !== '' ? " ({$speed})" : '') .
                ($price !== '' ? " - {$price}" : '') . "\n" .
                "Subscription expires in {$milestone['label']}.\n" .
                "Expiry: {$expiryDisplay}.";

            $sms = sendExpirySms(
                $adminPhone,
                $adminMessage,
                $smsSettings
            );

            if ($sms['success']) {

                recordNotification(
                    $conn,
                    $userId,
                    $expiryAt,
                    $milestone['key'],
                    'admin'
                );

                $totalSent++;

                createLog(
                    $conn,
                    'sms',
                    'admin_expiry_reminder_sent',
                    "Admin notified that {$user['username']} expires in {$milestone['label']}.",
                    'info',
                    $userId
                );

            } else {

                $totalFailed++;

                createLog(
                    $conn,
                    'sms',
                    'admin_expiry_reminder_failed',
                    "Failed admin {$milestone['label']} reminder for {$user['username']}: " .
                    ($sms['error'] ?? 'Unknown SMS error'),
                    'error',
                    $userId
                );
            }
        }
    }

    $stmt->close();
}

/* =========================================================
   6. EXPIRY MESSAGE
   ========================================================= */

/*
 * Process users whose expiry has arrived in the last 10 minutes.
 * The expiry reminder is sent before changing the account to inactive.
 */
$expiryStart = date(
    'Y-m-d H:i:s',
    $now - $windowSeconds
);

$expiryEnd = date(
    'Y-m-d H:i:s',
    $now
);

$stmt = $conn->prepare("
    SELECT
        u.id,
        u.username,
        u.first_name,
        u.phone_number,
        u.Expiry,
        u.status,
        p.package_name,
        p.speed
    FROM users u
    LEFT JOIN packages p ON p.id = u.package_id
    WHERE u.status = 'active'
      AND u.Expiry BETWEEN ? AND ?
");

$stmt->bind_param('ss', $expiryStart, $expiryEnd);
$stmt->execute();

$result = $stmt->get_result();

while ($user = $result->fetch_assoc()) {

    $userId = (int)$user['id'];
    $expiryAt = $user['Expiry'];
    $clientPhone = formatKenyanPhone($user['phone_number']);

    $packageName = $user['package_name'] ?? 'your package';
    $clientName = trim($user['first_name'] ?? '');

    if ($clientName === '') {
        $clientName = $user['username'];
    }

    $expiryDisplay = date(
        'd M Y, H:i',
        strtotime($expiryAt)
    );

    $clientMessage =
        "Hello {$clientName},\n" .
        "Your D-LINK NETWORK {$packageName} subscription has expired.\n" .
        "Expiry: {$expiryDisplay}.\n" .
        "Please renew your subscription to restore/continue service.";

    if ($clientPhone !== '' &&
        !notificationAlreadySent(
            $conn,
            $userId,
            $expiryAt,
            'expired',
            'client'
        )
    ) {

        $sms = sendExpirySms(
            $clientPhone,
            $clientMessage,
            $smsSettings
        );

        if ($sms['success']) {

            recordNotification(
                $conn,
                $userId,
                $expiryAt,
                'expired',
                'client'
            );

            $totalSent++;

            createLog(
                $conn,
                'sms',
                'subscription_expired_sms_sent',
                "Expiry SMS sent to client {$user['username']}.",
                'info',
                $userId
            );

        } else {

            $totalFailed++;

            createLog(
                $conn,
                'sms',
                'subscription_expired_sms_failed',
                "Failed expiry SMS for {$user['username']}: " .
                ($sms['error'] ?? 'Unknown SMS error'),
                'error',
                $userId
            );
        }
    }

    if ($adminPhone !== '' &&
        !notificationAlreadySent(
            $conn,
            $userId,
            $expiryAt,
            'expired',
            'admin'
        )
    ) {

        $adminMessage =
            "D-LINK NETWORK EXPIRY ALERT\n" .
            "Client: {$user['username']}\n" .
            "Phone: {$clientPhone}\n" .
            "Package: {$packageName}\n" .
            "Status: SUBSCRIPTION EXPIRED\n" .
            "Expiry: {$expiryDisplay}.";

        $sms = sendExpirySms(
            $adminPhone,
            $adminMessage,
            $smsSettings
        );

        if ($sms['success']) {

            recordNotification(
                $conn,
                $userId,
                $expiryAt,
                'expired',
                'admin'
            );

            $totalSent++;

            createLog(
                $conn,
                'sms',
                'admin_subscription_expired_sms_sent',
                "Admin notified that {$user['username']} has expired.",
                'info',
                $userId
            );

        } else {

            $totalFailed++;

            createLog(
                $conn,
                'sms',
                'admin_subscription_expired_sms_failed',
                "Failed admin expiry SMS for {$user['username']}: " .
                ($sms['error'] ?? 'Unknown SMS error'),
                'error',
                $userId
            );
        }
    }

    /*
     * Expire the account after the expiry notifications have been
     * attempted. This uses the exact Expiry timestamp.
     */
    $update = $conn->prepare("
        UPDATE users
        SET status = 'inactive'
        WHERE id = ?
          AND status = 'active'
          AND Expiry <= NOW()
    ");

    $update->bind_param('i', $userId);
    $update->execute();
    $update->close();
}

$stmt->close();

createLog(
    $conn,
    'system',
    'expiry_reminder_job_completed',
    "Expiry reminder job completed. SMS sent: {$totalSent}; SMS failed: {$totalFailed}.",
    $totalFailed > 0 ? 'warning' : 'info'
);

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'sent' => $totalSent,
    'failed' => $totalFailed,
    'time' => date('Y-m-d H:i:s')
]);
?>
