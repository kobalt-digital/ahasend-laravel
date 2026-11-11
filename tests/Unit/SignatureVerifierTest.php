<?php

declare(strict_types=1);

use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;
use KobaltDigital\AhaSend\Exceptions\WebhookVerificationFailed;
use KobaltDigital\AhaSend\Webhooks\SignatureVerifier;

const SIGNED_AT = 1730057863;

beforeEach(function (): void {
    $this->body = '{"type":"message.delivered","timestamp":"2024-10-27T19:35:58Z","data":{}}';
    $this->verifier = new SignatureVerifier(['aha-whsec-test']);
});

it('accepts a delivery with a valid signature', function (): void {
    $this->verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, sign($this->body, 'evt_1', SIGNED_AT), SIGNED_AT);
})->throwsNoExceptions();

it('matches a signature computed outside of this package', function (): void {
    // printf '%s' "evt_1.1730057863.$body" | openssl dgst -sha256 -hmac aha-whsec-test -binary | base64
    $this->verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, 'v1,f9wWm0JLeDHIU0DX+kuUbdfAHCeIi8rJg1Yf+XMdCww=', SIGNED_AT);
})->throwsNoExceptions();

it('uses the secret as it is, without decoding it', function (): void {
    $secret = base64_encode('raw-secret');
    $verifier = new SignatureVerifier([$secret]);

    $decoded = 'v1,'.base64_encode(hash_hmac('sha256', 'evt_1.'.SIGNED_AT.'.'.$this->body, 'raw-secret', true));

    expect(fn () => $verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, $decoded, SIGNED_AT))
        ->toThrow(WebhookVerificationFailed::class);

    $verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, sign($this->body, 'evt_1', SIGNED_AT, $secret), SIGNED_AT);
});

it('accepts a delivery when any of several signatures matches', function (): void {
    $signatures = 'v1,'.base64_encode('nope').' '.sign($this->body, 'evt_1', SIGNED_AT).' v2,ignored';

    $this->verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, $signatures, SIGNED_AT);
})->throwsNoExceptions();

it('accepts a delivery signed with any of the configured secrets', function (): void {
    $verifier = new SignatureVerifier(['aha-whsec-old', 'aha-rsec-route']);

    $verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, sign($this->body, 'evt_1', SIGNED_AT, 'aha-rsec-route'), SIGNED_AT);
})->throwsNoExceptions();

it('rejects a delivery signed with another secret', function (): void {
    $this->verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, sign($this->body, 'evt_1', SIGNED_AT, 'aha-whsec-other'), SIGNED_AT);
})->throws(WebhookVerificationFailed::class, 'No webhook signature matched');

it('rejects a delivery whose body was changed', function (): void {
    $signature = sign($this->body, 'evt_1', SIGNED_AT);

    $this->verifier->verify(str_replace('delivered', 'bounced', $this->body), 'evt_1', (string) SIGNED_AT, $signature, SIGNED_AT);
})->throws(WebhookVerificationFailed::class, 'No webhook signature matched');

it('rejects a delivery whose id was changed', function (): void {
    $this->verifier->verify($this->body, 'evt_2', (string) SIGNED_AT, sign($this->body, 'evt_1', SIGNED_AT), SIGNED_AT);
})->throws(WebhookVerificationFailed::class, 'No webhook signature matched');

it('rejects a signature without a supported version', function (): void {
    $signature = str_replace('v1,', 'v2,', sign($this->body, 'evt_1', SIGNED_AT));

    $this->verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, $signature, SIGNED_AT);
})->throws(WebhookVerificationFailed::class, 'No webhook signature matched');

it('rejects a delivery with a missing header', function (?string $id, ?string $timestamp, ?string $signature): void {
    $this->verifier->verify($this->body, $id, $timestamp, $signature, SIGNED_AT);
})->with([
    'no id' => [null, (string) SIGNED_AT, 'v1,abc'],
    'empty id' => ['', (string) SIGNED_AT, 'v1,abc'],
    'no timestamp' => ['evt_1', null, 'v1,abc'],
    'empty timestamp' => ['evt_1', '', 'v1,abc'],
    'no signature' => ['evt_1', (string) SIGNED_AT, null],
    'empty signature' => ['evt_1', (string) SIGNED_AT, ''],
])->throws(WebhookVerificationFailed::class, 'headers are required');

it('rejects a timestamp that is not a unix timestamp', function (string $timestamp): void {
    $this->verifier->verify($this->body, 'evt_1', $timestamp, 'v1,abc', SIGNED_AT);
})->with(['2024-10-27T19:35:58Z', '-1730057863', '1730057863.5', '1e9', ' 1730057863'])
    ->throws(WebhookVerificationFailed::class, 'not a valid Unix timestamp');

it('rejects a delivery outside of the tolerance', function (int $offset): void {
    $timestamp = SIGNED_AT + $offset;

    $this->verifier->verify($this->body, 'evt_1', (string) $timestamp, sign($this->body, 'evt_1', $timestamp), SIGNED_AT);
})->with([
    'too old' => [-301],
    'too new' => [301],
])->throws(WebhookVerificationFailed::class, 'outside the allowed tolerance');

it('accepts a delivery on the edge of the tolerance', function (int $offset): void {
    $timestamp = SIGNED_AT + $offset;

    $this->verifier->verify($this->body, 'evt_1', (string) $timestamp, sign($this->body, 'evt_1', $timestamp), SIGNED_AT);
})->with([-300, 300])->throwsNoExceptions();

it('uses a custom tolerance', function (): void {
    $verifier = new SignatureVerifier(['aha-whsec-test'], tolerance: 10);
    $timestamp = SIGNED_AT - 11;

    $verifier->verify($this->body, 'evt_1', (string) $timestamp, sign($this->body, 'evt_1', $timestamp), SIGNED_AT);
})->throws(WebhookVerificationFailed::class, 'outside the allowed tolerance');

it('compares against the current time by default', function (): void {
    $this->verifier->verify($this->body, 'evt_1', (string) time(), sign($this->body, 'evt_1', time()));
})->throwsNoExceptions();

it('refuses to verify without a secret', function (array $secrets): void {
    $verifier = new SignatureVerifier($secrets);

    $verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, sign($this->body, 'evt_1', SIGNED_AT, ''), SIGNED_AT);
})->with([
    'no secrets' => [[]],
    'an empty secret' => [['']],
])->throws(InvalidConfiguration::class, 'AHASEND_WEBHOOK_SECRET');

it('never verifies against an empty secret', function (): void {
    $verifier = new SignatureVerifier(['', 'aha-whsec-test']);

    $verifier->verify($this->body, 'evt_1', (string) SIGNED_AT, sign($this->body, 'evt_1', SIGNED_AT, ''), SIGNED_AT);
})->throws(WebhookVerificationFailed::class, 'No webhook signature matched');
