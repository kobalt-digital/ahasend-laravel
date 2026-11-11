<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A contact was removed from a list.
 *
 * Dispatched for the "contact.removed_from_list" webhook event.
 */
final class ContactRemovedFromList extends WebhookEvent {}
