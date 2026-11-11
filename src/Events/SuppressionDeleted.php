<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A suppression was deleted.
 *
 * Dispatched for the "suppression.deleted" webhook event.
 */
final class SuppressionDeleted extends WebhookEvent {}
