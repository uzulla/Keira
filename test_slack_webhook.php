<?php
// Simple Slack webhook test script
$webhookUrl = 'https://hooks.slack.com/services/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';
$channel = '#dev_null';

// Create test message
$message = [
    'channel' => $channel,
    'text' => '🚨 [TEST] This is a test alert from Keira Web Monitor at ' . date('Y-m-d H:i:s')
];

// Send to Slack
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($message))
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Slack notification sent. HTTP Status: $httpCode\n";
echo "Response: $result\n";

// Wait 3 seconds and send a recovery message
sleep(3);

// Create recovery message
$recoveryMessage = [
    'channel' => $channel,
    'text' => '✅ [TEST] This is a test recovery notification from Keira Web Monitor at ' . date('Y-m-d H:i:s')
];

// Send to Slack
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($recoveryMessage));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($recoveryMessage))
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Slack recovery notification sent. HTTP Status: $httpCode\n";
echo "Response: $result\n";
