<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use KobaltDigital\AhaSend\Exceptions\ApiException;
use KobaltDigital\AhaSend\Exceptions\ConnectionFailed;
use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;
use KobaltDigital\AhaSend\Resources\Messages;
use SensitiveParameter;
use Throwable;

/**
 * A small client for the AhaSend API v2.
 *
 * Paths are relative to the configured account, so `get('domains')` requests
 * `/v2/accounts/{account_id}/domains`.
 *
 * @see https://ahasend.com/docs/api-reference
 */
class AhaSend
{
    public const DEFAULT_BASE_URL = 'https://api.ahasend.com';

    /**
     * The longest we are willing to wait on a Retry-After header, in seconds.
     */
    private const MAX_RETRY_AFTER = 30;

    /**
     * @param  int  $retries  How often a failed request is retried. Zero disables retrying.
     *
     * @throws InvalidConfiguration When the base URL would send the API key over plain HTTP.
     */
    public function __construct(
        private readonly Factory $http,
        #[SensitiveParameter] private readonly string $apiKey,
        private readonly string $accountId,
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $timeout = 30,
        private readonly int $retries = 2,
    ) {
        if (! str_starts_with(strtolower($this->baseUrl), 'https://')) {
            throw InvalidConfiguration::insecureBaseUrl($this->baseUrl);
        }
    }

    public function messages(): Messages
    {
        return new Messages($this);
    }

    /**
     * Check that the API is reachable and the API key is accepted.
     *
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function ping(): array
    {
        return $this->send('GET', '/v2/ping');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $this->accountPath($path), ['query' => $query]);
    }

    /**
     * Every POST request carries an Idempotency-Key, so retrying it is safe.
     * Pass your own key to make a request idempotent across processes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function post(string $path, array $payload = [], ?string $idempotencyKey = null): array
    {
        return $this->send('POST', $this->accountPath($path), ['json' => $payload], [
            'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->send('PUT', $this->accountPath($path), ['json' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->send('DELETE', $this->accountPath($path), ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $headers
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $path, array $options = [], array $headers = []): array
    {
        try {
            $response = $this->request()->withHeaders($headers)->send($method, $path, array_filter($options));
        } catch (ConnectionException $exception) {
            throw ConnectionFailed::from($exception);
        }

        if ($response->failed()) {
            throw ApiException::fromResponse($response);
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->withoutRedirecting()
            ->withUserAgent('kobaltdigital/ahasend-laravel')
            ->retry(
                $this->retries + 1,
                fn (int $attempt, mixed $exception): int => $this->retryDelay($attempt, $exception),
                fn (Throwable $exception): bool => $this->shouldRetry($exception),
                throw: false,
            );
    }

    /**
     * Connection errors, rate limits and server errors are worth another try.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        return $exception->response->status() === 429 || $exception->response->serverError();
    }

    /**
     * The delay before the next attempt in milliseconds. Honours Retry-After
     * and falls back to exponential backoff.
     */
    private function retryDelay(int $attempt, mixed $exception): int
    {
        if ($exception instanceof RequestException) {
            $retryAfter = $this->retryAfter($exception->response);

            if ($retryAfter !== null) {
                return min($retryAfter, self::MAX_RETRY_AFTER) * 1000;
            }
        }

        return 250 * (2 ** ($attempt - 1));
    }

    private function retryAfter(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        return ctype_digit($retryAfter) ? (int) $retryAfter : null;
    }

    private function accountPath(string $path): string
    {
        return sprintf('/v2/accounts/%s/%s', rawurlencode($this->accountId), ltrim($path, '/'));
    }
}
