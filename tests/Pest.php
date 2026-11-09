<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use KobaltDigital\AhaSend\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * The URL of an account scoped endpoint, as the client should request it.
 */
function endpoint(string $path = ''): string
{
    return 'https://api.ahasend.com/v2/accounts/'.TestCase::ACCOUNT_ID.($path === '' ? '' : '/'.$path);
}

/**
 * @return array<string, mixed>
 */
function payload(string $name): array
{
    $contents = file_get_contents(__DIR__.'/Fixtures/'.$name.'.json');

    return json_decode((string) $contents, true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Sign a webhook body the way AhaSend does.
 */
function sign(string $body, string $id, int $timestamp, string $secret = TestCase::WEBHOOK_SECRET): string
{
    return 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $secret, true));
}

/**
 * Post a signed webhook delivery to the package route.
 *
 * @param  array<string, mixed>|string  $payload
 * @param  array<string, string>  $headers
 */
function postWebhook(array|string $payload, array $headers = [], string $uri = '/ahasend/webhook'): TestResponse
{
    $body = is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = time();

    $headers = array_merge([
        'webhook-id' => 'msg_2KWPBgLlAfxdpx2AI54pPJ85f4W',
        'webhook-timestamp' => (string) $timestamp,
        'webhook-signature' => sign($body, 'msg_2KWPBgLlAfxdpx2AI54pPJ85f4W', $timestamp),
        'Content-Type' => 'application/json',
    ], $headers);

    $server = [];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', $uri, server: $server, content: $body);
}
