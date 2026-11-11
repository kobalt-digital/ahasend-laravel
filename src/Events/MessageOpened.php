<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A recipient opened a message.
 *
 * Dispatched for the "message.opened" webhook event.
 */
final class MessageOpened extends WebhookEvent {}
