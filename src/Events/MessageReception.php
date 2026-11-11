<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * AhaSend accepted a message for delivery.
 *
 * Dispatched for the "message.reception" webhook event.
 */
final class MessageReception extends WebhookEvent {}
