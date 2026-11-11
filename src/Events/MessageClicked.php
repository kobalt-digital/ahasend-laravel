<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A recipient clicked a tracked link in a message.
 *
 * Dispatched for the "message.clicked" webhook event.
 */
final class MessageClicked extends WebhookEvent {}
