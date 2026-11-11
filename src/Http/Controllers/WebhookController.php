<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use KobaltDigital\AhaSend\Enums\WebhookEventType;
use KobaltDigital\AhaSend\Events\WebhookReceived;
use Symfony\Component\HttpFoundation\Response;

final class WebhookController
{
    public function __construct(private readonly Dispatcher $events) {}

    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        $type = is_array($payload) ? ($payload['type'] ?? null) : null;

        if (! is_array($payload) || ! is_string($type)) {
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed> $payload */
        $headers = $this->headers($request);

        $this->events->dispatch(new WebhookReceived($payload, $headers));

        // AhaSend expects a 2xx for event types we do not know, so those
        // only reach listeners through the WebhookReceived event.
        $event = WebhookEventType::tryFrom($type)?->eventClass();

        if ($event !== null) {
            $this->events->dispatch(new $event($payload, $headers));
        }

        return new Response('Webhook handled', Response::HTTP_OK);
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach (['webhook-id', 'webhook-timestamp', 'webhook-signature'] as $name) {
            $value = $request->headers->get($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
