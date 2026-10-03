<?php

function sendExpirySMS($conn, $phone, $message, $user_id, $recipient_name, $sms_type)
{
    /*
     * LOAD ACTIVE TALKSASA SETTINGS
     */
    $stmt = $conn->prepare("
        SELECT api_token, sender_id
        FROM sms_settings
        WHERE is_active = 1
        LIMIT 1
    ");

    if (!$stmt) {
        return [
            'success' => false,
            'response' => 'Failed to prepare SMS settings query.'
        ];
    }

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return [
            'success' => false,
            'response' => 'SMS module is disabled or not configured.'
        ];
    }

    $settings = $result->fetch_assoc();

    /*
     * FORMAT KENYAN PHONE NUMBER
     */
    $phone = trim($phone);

    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }

    /*
     * PREPARE TALKSASA REQUEST
     */
    $data = [
        "recipient" => $phone,
        "sender_id" => $settings['sender_id'],
        "message" => $message
    ];

    /*
     * SEND TO TALKSASA
     */
    $ch = curl_init();

    curl_setopt(
        $ch,
        CURLOPT_URL,
        "https://bulksms.talksasa.com/api/v3/sms/send"
    );

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($data)
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $settings['api_token'],
        "Content-Type: application/json",
        "Accept: application/json"
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    $curl_error = curl_error($ch);

    curl_close($ch);

    /*
     * DETERMINE STATUS
     */
    if ($curl_error) {
        $status = 'Failed';
        $api_response = $curl_error;
        $success = false;
    } else {
        $status = 'Sent';
        $api_response = $response;
        $success = true;
    }

    /*
     * SAVE SMS HISTORY
     */
    $campaign_id = null;
    $cost = 0;

    $stmt = $conn->prepare("
        INSERT INTO sms_history
        (
            campaign_id,
            user_id,
            phone,
            recipient_name,
            message,
            sms_type,
            provider_response,
            status,
            cost,
            sent_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {

        /*
         * Admin/system generated SMS
         */
        $sent_by = 1;

        $stmt->bind_param(
            "iissssssdi",
            $campaign_id,
            $user_id,
            $phone,
            $recipient_name,
            $message,
            $sms_type,
            $api_response,
            $status,
            $cost,
            $sent_by
        );

        $stmt->execute();
    }

    return [
        'success' => $success,
        'response' => $api_response,
        'phone' => $phone
    ];
}