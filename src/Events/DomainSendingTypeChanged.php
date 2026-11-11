<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * The sending type of a domain changed between transactional and marketing.
 *
 * Dispatched for the "domain.sending_type_changed" webhook event.
 */
final class DomainSendingTypeChanged extends WebhookEvent {}
