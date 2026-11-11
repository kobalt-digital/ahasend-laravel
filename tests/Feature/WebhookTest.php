<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use KobaltDigital\AhaSend\Enums\WebhookEventType;
use KobaltDigital\AhaSend\Events\MessageBounced;
use KobaltDigital\AhaSend\Events\MessageDelivered;
use KobaltDigital\AhaSend\Events\MessageRouting;
use KobaltDigital\AhaSend\Events\WebhookReceived;
use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;
use KobaltDigital\AhaSend\Webhooks\SignatureVerifier;

beforeEach(function (): void {
    Event::fake();
});

it('dispatches an event for a webhook', function (): void {
    postWebhook(payload('message-bounced'))
        ->assertOk()
        ->assertContent('Webhook handled');

    Event::assertDispatched(fn (MessageBounced $event): bool => $event->payload === payload('message-bounced')
        && $event->data('recipient') === 'recipient@example.com'
        && $event->deliveryId() === 'msg_2KWPBgLlAfxdpx2AI54pPJ85f4W'
        && array_keys($event->headers) === ['webhook-id', 'webhook-timestamp', 'webhook-signature']);

    Event::assertNotDispatched(MessageDelivered::class);
});

it('dispatches the matching event for every event type', function (WebhookEventType $type): void {
    postWebhook(['type' => $type->value, 'timestamp' => '2024-05-06T10:05:16Z', 'data' => []])->assertOk();

    Event::assertDispatchedTimes($type->eventClass(), 1);
    Event::assertDispatchedTimes(WebhookReceived::class, 1);
})->with(WebhookEventType::cases());

it('dispatches an event for an inbound message', function (): void {
    postWebhook(payload('message-routing'))->assertOk();

    Event::assertDispatched(fn (MessageRouting $event): bool => $event->data('subject') === 'Help with my account');
});

it('always dispatches the catch-all event', function (): void {
    postWebhook(payload('message-bounced'))->assertOk();

    Event::assertDispatched(fn (WebhookReceived $event): bool => $event->type() === 'message.bounced');
});

it('acknowledges an event type it does not know', function (): void {
    postWebhook(['type' => 'message.teleported', 'data' => []])->assertOk();

    Event::assertDispatched(fn (WebhookReceived $event): bool => $event->type() === 'message.teleported');
    Event::assertDispatchedTimes(WebhookReceived::class, 1);
});

it('refuses a payload it cannot read', function (string $body): void {
    postWebhook($body)
        ->assertBadRequest()
        ->assertContent('Invalid payload');

    Event::assertNotDispatched(WebhookReceived::class);
})->with([
    'not json' => ['type=message.bounced'],
    'a json string' => ['"message.bounced"'],
    'no type' => ['{"data":{}}'],
    'a type that is not a string' => ['{"type":["message.bounced"]}'],
    'empty' => [''],
]);

it('refuses a webhook with a wrong signature', function (): void {
    postWebhook(payload('message-bounced'), ['webhook-signature' => 'v1,'.base64_encode('forged')])
        ->assertForbidden();

    Event::assertNotDispatched(WebhookReceived::class);
});

it('refuses a webhook without a signature', function (): void {
    $this->postJson('/ahasend/webhook', payload('message-bounced'))->assertForbidden();

    Event::assertNotDispatched(WebhookReceived::class);
});

it('refuses a webhook that is replayed later', function (): void {
    $body = json_encode(payload('message-bounced'), JSON_THROW_ON_ERROR);
    $timestamp = time() - 301;

    postWebhook($body, [
        'webhook-timestamp' => (string) $timestamp,
        'webhook-signature' => sign($body, 'msg_2KWPBgLlAfxdpx2AI54pPJ85f4W', $timestamp),
    ])->assertForbidden();

    Event::assertNotDispatched(WebhookReceived::class);
});

it('respects the configured tolerance', function (): void {
    config()->set('ahasend.webhook.tolerance', 600);

    $body = json_encode(payload('message-bounced'), JSON_THROW_ON_ERROR);
    $timestamp = time() - 301;

    postWebhook($body, [
        'webhook-timestamp' => (string) $timestamp,
        'webhook-signature' => sign($body, 'msg_2KWPBgLlAfxdpx2AI54pPJ85f4W', $timestamp),
    ])->assertOk();
});

it('falls back to the default tolerance', function (): void {
    config()->set('ahasend.webhook.tolerance', 'soon');

    $body = json_encode(payload('message-bounced'), JSON_THROW_ON_ERROR);
    $timestamp = time() - 301;

    postWebhook($body, [
        'webhook-timestamp' => (string) $timestamp,
        'webhook-signature' => sign($body, 'msg_2KWPBgLlAfxdpx2AI54pPJ85f4W', $timestamp),
    ])->assertForbidden();
});

it('accepts webhooks signed with any of the configured secrets', function (mixed $secrets): void {
    config()->set('ahasend.webhook.secret', $secrets);

    $body = json_encode(payload('message-routing'), JSON_THROW_ON_ERROR);

    postWebhook($body, [
        'webhook-signature' => sign($body, 'msg_2KWPBgLlAfxdpx2AI54pPJ85f4W', time(), 'aha-rsec-route'),
    ])->assertOk();
})->with([
    'comma separated' => ['aha-whsec-test, aha-rsec-route'],
    'a list' => [['aha-whsec-test', 'aha-rsec-route']],
]);

it('fails loudly when no webhook secret is configured', function (mixed $secret): void {
    config()->set('ahasend.webhook.secret', $secret);

    $this->withoutExceptionHandling();

    expect(fn () => postWebhook(payload('message-bounced')))->toThrow(InvalidConfiguration::class, 'AHASEND_WEBHOOK_SECRET');

    Event::assertNotDispatched(WebhookReceived::class);
})->with([
    'null' => [null],
    'an empty string' => [''],
    'only separators' => [' , '],
    'an empty list' => [[]],
    'a list without strings' => [[null, 42]],
]);

it('never answers a webhook without verifying it', function (): void {
    config()->set('ahasend.webhook.secret', null);

    postWebhook(payload('message-bounced'))->assertServerError();

    Event::assertNotDispatched(WebhookReceived::class);
});

it('resolves the verifier once', function (): void {
    expect(app(SignatureVerifier::class))->toBe(app(SignatureVerifier::class));
});
