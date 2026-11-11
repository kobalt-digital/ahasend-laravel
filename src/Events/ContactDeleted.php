<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A contact was deleted.
 *
 * Dispatched for the "contact.deleted" webhook event.
 */
final class ContactDeleted extends WebhookEvent {}
