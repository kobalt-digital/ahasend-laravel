<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A recipient unsubscribed through a campaign email.
 *
 * Dispatched for the "message.unsubscribed" webhook event.
 */
final class MessageUnsubscribed extends WebhookEvent {}
