<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A recipient reported a campaign email as spam.
 *
 * Dispatched for the "message.complained" webhook event.
 */
final class MessageComplained extends WebhookEvent {}
