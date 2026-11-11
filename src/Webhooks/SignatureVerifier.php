<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Webhooks;

use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;
use KobaltDigital\AhaSend\Exceptions\WebhookVerificationFailed;
use SensitiveParameter;

/**
 * Verifies the signature of AhaSend webhook and route deliveries.
 *
 * AhaSend follows the Standard Webhooks specification, with one difference:
 * the HMAC key is the literal secret, prefix included, and is never
 * Base64-decoded.
 *
 * @see https://ahasend.com/docs/api-reference/webhooks/security
 */
final class SignatureVerifier
{
    public const DEFAULT_TOLERANCE = 300;

    /**
     * @var list<string>
     */
    private readonly array $secrets;

    /**
     * @param  array<array-key, string>  $secrets  Every secret that may have signed the delivery.
     * @param  int  $tolerance  Allowed difference with the delivery timestamp, in seconds.
     */
    public function __construct(
        #[SensitiveParameter] array $secrets,
        private readonly int $tolerance = self::DEFAULT_TOLERANCE,
    ) {
        // An empty secret would make every signature forgeable.
        $this->secrets = array_values(array_filter($secrets, static fn (string $secret): bool => $secret !== ''));
    }

    /**
     * @param  string  $payload  The raw, unparsed request body.
     *
     * @throws WebhookVerificationFailed
     * @throws InvalidConfiguration When there is no secret to verify with.
     */
    public function verify(string $payload, ?string $id, ?string $timestamp, ?string $signatures, ?int $now = null): void
    {
        if ($this->secrets === []) {
            throw InvalidConfiguration::webhookSecretMissing();
        }

        if ($id === null || $id === '' || $timestamp === null || $timestamp === '' || $signatures === null || $signatures === '') {
            throw WebhookVerificationFailed::missingHeaders();
        }

        if (! ctype_digit($timestamp)) {
            throw WebhookVerificationFailed::invalidTimestamp();
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $this->tolerance) {
            throw WebhookVerificationFailed::timestampOutsideTolerance();
        }

        $signedContent = "{$id}.{$timestamp}.{$payload}";

        foreach ($this->secrets as $secret) {
            $expected = base64_encode(hash_hmac('sha256', $signedContent, $secret, true));

            foreach ($this->candidates($signatures) as $candidate) {
                if (hash_equals($expected, $candidate)) {
                    return;
                }
            }
        }

        throw WebhookVerificationFailed::signatureMismatch();
    }

    /**
     * The header holds one or more space separated "v1,<signature>" values.
     *
     * @return list<string>
     */
    private function candidates(string $signatures): array
    {
        $candidates = [];

        foreach (explode(' ', $signatures) as $signature) {
            if (str_starts_with($signature, 'v1,')) {
                $candidates[] = substr($signature, 3);
            }
        }

        return $candidates;
    }
}
