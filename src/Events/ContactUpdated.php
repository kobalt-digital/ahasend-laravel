<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A field of a contact changed.
 *
 * Dispatched for the "contact.updated" webhook event.
 */
final class ContactUpdated extends WebhookEvent {}
