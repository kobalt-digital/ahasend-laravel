<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Arr;

/**
 * A verified webhook delivery from AhaSend.
 */
abstract class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload  The decoded JSON body of the delivery.
     * @param  array<string, string>  $headers  The Standard Webhooks headers of the delivery.
     */
    final public function __construct(
        public readonly array $payload,
        public readonly array $headers = [],
    ) {}

    /**
     * The event type, for example "message.delivered".
     */
    public function type(): string
    {
        $type = $this->payload['type'] ?? '';

        return is_string($type) ? $type : '';
    }

    /**
     * Read from the event specific data using "dot" notation.
     */
    public function data(?string $key = null, mixed $default = null): mixed
    {
        return Arr::get($this->payload, $key === null ? 'data' : "data.{$key}", $default);
    }

    /**
     * The ID of this delivery. It stays the same across retries, which makes
     * it a good key for deduplication.
     */
    public function deliveryId(): ?string
    {
        return $this->headers['webhook-id'] ?? null;
    }

    /**
     * The moment the event happened. Deliveries can arrive out of order, so
     * sort on this instead of on arrival time.
     */
    public function occurredAt(): ?CarbonImmutable
    {
        $timestamp = $this->payload['timestamp'] ?? null;

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
