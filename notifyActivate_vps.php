<?php
function notifyVPSActivate($data) {

    $vpsUrl = "http://162.245.191.109/notify_activate.php";

    $payload = json_encode(array_merge($data, [
        "secret" => "MY_SECRET_123"
    ]));

    $ch = curl_init($vpsUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 10
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
