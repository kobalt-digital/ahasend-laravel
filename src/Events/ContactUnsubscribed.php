<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A contact unsubscribed from a list or from all lists.
 *
 * Dispatched for the "contact.unsubscribed" webhook event.
 */
final class ContactUnsubscribed extends WebhookEvent {}
