<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Epostix\Client;
use Epostix\Webhook\EpostixWebhookVerifier;

if (!class_exists(Client::class)) {
    fwrite(STDERR, "Epostix\\Client did not autoload\n");
    exit(1);
}

$secret = 'whsec_dGVzdHNlY3JldA';
$timestamp = time();
$payload = '{"id":"evt_1","type":"email.delivered","created_at":"2026-09-06T10:00:00Z","data":{"email_id":"eml_1"}}';
$signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

$verifier = new EpostixWebhookVerifier([$secret]);

$verified = $verifier->verifyAndDecode($payload, [
    'Webhook-Id' => 'whl_1',
    'Webhook-Timestamp' => (string) $timestamp,
    'Webhook-Signature' => 't=' . $timestamp . ',v1=' . $signature,
]);

if ($verified->event->id !== 'evt_1') {
    fwrite(STDERR, "webhook verification returned the wrong event\n");
    exit(1);
}

echo "install smoke ok\n";
