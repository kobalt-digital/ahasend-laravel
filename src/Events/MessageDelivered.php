<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * The recipient server accepted a message.
 *
 * Dispatched for the "message.delivered" webhook event.
 */
final class MessageDelivered extends WebhookEvent {}
