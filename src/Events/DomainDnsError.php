<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Events;

/**
 * A sending domain has a DNS error.
 *
 * Dispatched for the "domain.dns_error" webhook event.
 */
final class DomainDnsError extends WebhookEvent {}
