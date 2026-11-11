<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A contact reported a campaign email as spam.
 *
 * Dispatched for the "contact.complained" webhook event.
 */
final class ContactComplained extends WebhookEvent {}
