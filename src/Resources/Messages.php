<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Resources;

use KobaltDigital\AhaSend\AhaSend;
use KobaltDigital\AhaSend\Exceptions\ApiException;
use KobaltDigital\AhaSend\Exceptions\ConnectionFailed;

/**
 * @see https://ahasend.com/docs/api-reference/messages/create-message
 *
 * @phpstan-type SendResponse array{
 *     object: 'list',
 *     data: list<array{
 *         object: 'message',
 *         id: string|null,
 *         message_id_header?: string|null,
 *         recipient: array{email: string, name?: string|null},
 *         status: 'queued'|'scheduled'|'error',
 *         error: string|null,
 *         schedule?: array{first_attempt?: string, expires?: string},
 *     }>,
 * }
 */
final class Messages
{
    public function __construct(private readonly AhaSend $client) {}

    /**
     * Send a message. Every recipient gets a separate copy, and
     * substitutions are available per recipient.
     *
     * @param  array<string, mixed>  $payload
     * @return SendResponse
     *
     * @throws ApiException|ConnectionFailed
     */
    public function send(array $payload, ?string $idempotencyKey = null): array
    {
        /** @var SendResponse */
        return $this->client->post('messages', $payload, $idempotencyKey);
    }

    /**
     * Send a conversational message with real To, Cc and Bcc headers.
     *
     * @param  array<string, mixed>  $payload
     * @return SendResponse
     *
     * @throws ApiException|ConnectionFailed
     */
    public function sendConversation(array $payload, ?string $idempotencyKey = null): array
    {
        /** @var SendResponse */
        return $this->client->post('messages/conversation', $payload, $idempotencyKey);
    }

    /**
     * Send a message based on one of the account's transactional templates.
     *
     * @param  array<string, mixed>  $payload
     * @return SendResponse
     *
     * @throws ApiException|ConnectionFailed
     */
    public function sendTemplate(array $payload, ?string $idempotencyKey = null): array
    {
        /** @var SendResponse */
        return $this->client->post('messages/template', $payload, $idempotencyKey);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function get(string $id): array
    {
        return $this->client->get('messages/'.rawurlencode($id));
    }

    /**
     * List message summaries, optionally filtered by status, sender,
     * recipient, subject, tags or time range.
     *
     * @param  array<string, mixed>  $filters
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function list(array $filters = []): array
    {
        return $this->client->get('messages', $filters);
    }

    /**
     * Cancel a scheduled message that has not been sent yet.
     *
     * @return array<array-key, mixed>
     *
     * @throws ApiException|ConnectionFailed
     */
    public function cancel(string $id): array
    {
        return $this->client->delete('messages/'.rawurlencode($id).'/cancel');
    }
}
