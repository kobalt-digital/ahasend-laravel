<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A contact confirmed a list membership or resubscribed.
 *
 * Dispatched for the "contact.subscribed" webhook event.
 */
final class ContactSubscribed extends WebhookEvent {}
