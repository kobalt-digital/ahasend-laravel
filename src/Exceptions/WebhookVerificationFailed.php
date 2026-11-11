<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Exceptions;

final class WebhookVerificationFailed extends AhaSendException
{
    public static function missingHeaders(): self
    {
        return new self('The webhook-id, webhook-timestamp and webhook-signature headers are required.');
    }

    public static function invalidTimestamp(): self
    {
        return new self('The webhook timestamp is not a valid Unix timestamp.');
    }

    public static function timestampOutsideTolerance(): self
    {
        return new self('The webhook timestamp is outside the allowed tolerance.');
    }

    public static function signatureMismatch(): self
    {
        return new self('No webhook signature matched the expected signature.');
    }
}
