<?php

declare(strict_types=1);

use KobaltDigital\AhaSend\Enums\WebhookEventType;
use KobaltDigital\AhaSend\Events\MessageBounced;
use KobaltDigital\AhaSend\Events\WebhookEvent;
use KobaltDigital\AhaSend\Events\WebhookReceived;

it('exposes the type of the event', function (): void {
    expect((new MessageBounced(payload('message-bounced')))->type())->toBe('message.bounced');
});

it('falls back to an empty type', function (array $payload): void {
    expect((new WebhookReceived($payload))->type())->toBe('');
})->with([
    'missing' => [[]],
    'not a string' => [['type' => 42]],
]);

it('reads the event data', function (): void {
    $event = new MessageBounced(payload('message-bounced'));

    expect($event->data())->toBeArray()->toHaveKey('recipient', 'recipient@example.com')
        ->and($event->data('recipient'))->toBe('recipient@example.com')
        ->and($event->data('delivery_attempt.smtp_code'))->toBe(550)
        ->and($event->data('missing'))->toBeNull()
        ->and($event->data('missing', 'fallback'))->toBe('fallback');
});

it('exposes the delivery id', function (): void {
    expect((new MessageBounced([], ['webhook-id' => 'evt_1']))->deliveryId())->toBe('evt_1')
        ->and((new MessageBounced([]))->deliveryId())->toBeNull();
});

it('parses the moment the event happened', function (): void {
    $occurredAt = (new MessageBounced(payload('message-bounced')))->occurredAt();

    expect($occurredAt?->toIso8601String())->toBe('2024-05-06T10:05:16+00:00')
        ->and($occurredAt?->micro)->toBe(687031);
});

it('has no moment without a usable timestamp', function (array $payload): void {
    expect((new WebhookReceived($payload))->occurredAt())->toBeNull();
})->with([
    'missing' => [[]],
    'empty' => [['timestamp' => '']],
    'not a string' => [['timestamp' => 1714989916]],
    'not a date' => [['timestamp' => 'yesterday-ish at noon']],
]);

it('maps every event type to its own event class', function (WebhookEventType $type): void {
    $class = $type->eventClass();

    expect(is_subclass_of($class, WebhookEvent::class))->toBeTrue()
        ->and(class_basename($class))->toBe($type->name);
})->with(WebhookEventType::cases());

it('does not share an event class between event types', function (): void {
    $classes = array_map(fn (WebhookEventType $type): string => $type->eventClass(), WebhookEventType::cases());

    expect(array_unique($classes))->toHaveCount(count(WebhookEventType::cases()));
});

it('names the event after the event type', function (WebhookEventType $type): void {
    expect($type->name)->toBe(str($type->value)->replace('.', '_')->studly()->toString());
})->with(WebhookEventType::cases());
