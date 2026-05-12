<?php

function notifyVPS($data) {

    $data['secret'] = 'MY_SECRET_123';

    $payload = json_encode($data);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n" .
                         "Content-Length: " . strlen($payload) . "\r\n",
            'content' => $payload,
            'timeout' => 10
        ]
    ]);

    return @file_get_contents(
        "http://162.245.191.109/payment-notify.php",
        false,
        $context
    );
}
