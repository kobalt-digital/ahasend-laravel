<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A contact was added to a list.
 *
 * Dispatched for the "contact.added_to_list" webhook event.
 */
final class ContactAddedToList extends WebhookEvent {}
