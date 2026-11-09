<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Thrown when the AhaSend API answers with an error status.
 */
final class ApiException extends AhaSendException
{
    /**
     * @param  array<array-key, mixed>  $body  The decoded JSON body of the response, if any.
     * @param  int|null  $retryAfter  Seconds to wait before retrying, taken from the Retry-After header.
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $body = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $reason = $body['message'] ?? null;
        $reason = is_string($reason) && $reason !== '' ? $reason : 'Unknown error';

        $retryAfter = $response->header('Retry-After');

        return new self(
            sprintf('The AhaSend API responded with status %d: %s', $response->status(), $reason),
            $response->status(),
            $body,
            ctype_digit($retryAfter) ? (int) $retryAfter : null,
        );
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500;
    }
}
