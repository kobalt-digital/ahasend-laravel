<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * The recipient server rejected a message.
 *
 * Dispatched for the "message.bounced" webhook event.
 */
final class MessageBounced extends WebhookEvent {}
