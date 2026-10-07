<?php

// Centipid Webhook - Initial Capture Only

date_default_timezone_set('Africa/Nairobi');

// Receive the raw request body
$rawPayload = file_get_contents('php://input');

// Receive request headers
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Create a simple log directory
$logDir = __DIR__ . '/logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Save the raw Centipid request
$logData = [
    'received_at' => date('Y-m-d H:i:s'),
    'method'      => $_SERVER['REQUEST_METHOD'] ?? '',
    'headers'     => $headers,
    'raw_payload' => $rawPayload
];

file_put_contents(
    $logDir . '/centipid_webhook.log',
    json_encode($logData, JSON_PRETTY_PRINT) . PHP_EOL . str_repeat('-', 80) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

// Tell Centipid the request was received
http_response_code(200);

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Webhook received'
]);
