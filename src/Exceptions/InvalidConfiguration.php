<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Exceptions;

final class InvalidConfiguration extends AhaSendException
{
    public static function apiKeyMissing(): self
    {
        return new self(
            'The AhaSend API key is missing. Set the AHASEND_API_KEY environment variable or the "ahasend.api_key" config value.'
        );
    }

    public static function accountIdMissing(): self
    {
        return new self(
            'The AhaSend account ID is missing. Set the AHASEND_ACCOUNT_ID environment variable or the "ahasend.account_id" config value.'
        );
    }

    public static function insecureBaseUrl(string $baseUrl): self
    {
        return new self(sprintf(
            'The AhaSend base URL must use HTTPS, because every request carries the API key. Got "%s".',
            $baseUrl,
        ));
    }

    public static function webhookSecretMissing(): self
    {
        return new self(
            'The AhaSend webhook secret is missing. Set the AHASEND_WEBHOOK_SECRET environment variable or the "ahasend.webhook.secret" config value.'
        );
    }
}
