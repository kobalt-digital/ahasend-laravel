<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A suppression was created or got a new reason.
 *
 * Dispatched for the "suppression.created" webhook event.
 */
final class SuppressionCreated extends WebhookEvent {}
