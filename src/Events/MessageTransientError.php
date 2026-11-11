<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A delivery attempt failed temporarily and will be retried.
 *
 * Dispatched for the "message.transient_error" webhook event.
 */
final class MessageTransientError extends WebhookEvent {}
