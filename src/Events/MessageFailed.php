<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * Delivery of a message failed for good.
 *
 * Dispatched for the "message.failed" webhook event.
 */
final class MessageFailed extends WebhookEvent {}
