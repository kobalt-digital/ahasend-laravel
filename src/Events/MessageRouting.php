<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * An inbound email matched one of the configured routes.
 *
 * Dispatched for the "message.routing" webhook event.
 */
final class MessageRouting extends WebhookEvent {}
