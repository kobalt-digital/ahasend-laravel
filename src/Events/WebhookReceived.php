<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * Dispatched for every verified webhook delivery, including event types
 * this package does not know about yet.
 */
final class WebhookReceived extends WebhookEvent {}
