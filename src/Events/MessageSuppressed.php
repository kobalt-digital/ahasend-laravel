<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A suppression kept a message from being sent.
 *
 * Dispatched for the "message.suppressed" webhook event.
 */
final class MessageSuppressed extends WebhookEvent {}
