<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Transport;

use KobaltDigital\AhaSend\AhaSend;
use KobaltDigital\AhaSend\Exceptions\AhaSendException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through the AhaSend API.
 *
 * Messages go to the "conversation" endpoint, because that is the endpoint
 * that keeps the To, Cc and Bcc recipients the way a mail client expects.
 *
 * @see https://ahasend.com/docs/api-reference/messages/create-conversation
 */
final class AhaSendTransport extends AbstractTransport implements Stringable
{
    /**
     * Headers that steer the API request and never reach the recipient.
     */
    public const HEADER_IDEMPOTENCY_KEY = 'X-AhaSend-Idempotency-Key';

    public const HEADER_SANDBOX = 'X-AhaSend-Sandbox';

    public const HEADER_SANDBOX_RESULT = 'X-AhaSend-Sandbox-Result';

    public const HEADER_TRACK_OPENS = 'X-AhaSend-Track-Opens';

    public const HEADER_TRACK_CLICKS = 'X-AhaSend-Track-Clicks';

    public const HEADER_SCHEDULED_AT = 'X-AhaSend-Scheduled-At';

    public const HEADER_EXPIRES_AT = 'X-AhaSend-Expires-At';

    /**
     * The header that carries the AhaSend message ID after sending.
     */
    public const HEADER_MESSAGE_ID = 'X-AhaSend-Message-ID';

    /**
     * Headers the API sets itself or receives through a dedicated field.
     */
    private const SKIPPED_HEADERS = [
        'from',
        'to',
        'cc',
        'bcc',
        'subject',
        'reply-to',
        'sender',
        'return-path',
        'content-type',
        'mime-version',
        'message-id',
    ];

    private const CONTROL_HEADERS = [
        self::HEADER_IDEMPOTENCY_KEY,
        self::HEADER_SANDBOX,
        self::HEADER_SANDBOX_RESULT,
        self::HEADER_TRACK_OPENS,
        self::HEADER_TRACK_CLICKS,
        self::HEADER_SCHEDULED_AT,
        self::HEADER_EXPIRES_AT,
    ];

    /**
     * @param  array{sandbox?: bool, track_opens?: bool|null, track_clicks?: bool|null}  $options  Defaults for every message sent through this mailer.
     */
    public function __construct(
        private readonly AhaSend $client,
        private readonly array $options = [],
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'ahasend';
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new TransportException('The AhaSend transport can only send messages that carry headers and a body.');
        }

        $email = MessageConverter::toEmail($original);

        try {
            $response = $this->client->messages()->sendConversation(
                $this->payload($email, $message->getEnvelope()),
                $this->header($email, self::HEADER_IDEMPOTENCY_KEY),
            );
        } catch (AhaSendException $exception) {
            throw new TransportException(
                sprintf('Request to the AhaSend API failed. Reason: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception,
            );
        }

        $accepted = [];
        $rejected = [];

        foreach ($response['data'] as $result) {
            if ($result['status'] === 'error' || $result['id'] === null) {
                $rejected[] = $result;
            } else {
                $accepted[] = $result;
            }
        }

        if ($accepted === []) {
            throw new TransportException(sprintf(
                'AhaSend did not accept the message for any recipient. Reason: %s',
                $rejected[0]['error'] ?? 'Unknown error',
            ));
        }

        foreach ($rejected as $result) {
            $this->getLogger()->warning('AhaSend did not accept the message for a recipient.', [
                'recipient' => $result['recipient']['email'],
                'error' => $result['error'],
            ]);
        }

        $original->getHeaders()->addTextHeader(self::HEADER_MESSAGE_ID, (string) $accepted[0]['id']);

        if (is_string($accepted[0]['message_id_header'] ?? null)) {
            $message->setMessageId(trim($accepted[0]['message_id_header'], '<>'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->address($email->getFrom()[0] ?? $envelope->getSender()),
            'to' => $this->addresses($email->getTo()),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'reply_to' => isset($email->getReplyTo()[0]) ? $this->address($email->getReplyTo()[0]) : null,
            'subject' => (string) $email->getSubject(),
            'text_content' => $email->getTextBody(),
            'html_content' => $email->getHtmlBody(),
            'attachments' => $this->attachments($email),
            'headers' => $this->headers($email),
            'tags' => $this->tags($email),
            'tracking' => $this->tracking($email),
            'schedule' => $this->schedule($email),
            'sandbox_result' => $this->header($email, self::HEADER_SANDBOX_RESULT),
        ];

        if ($this->boolean($email, self::HEADER_SANDBOX) ?? ($this->options['sandbox'] ?? false)) {
            $payload['sandbox'] = true;
        }

        return array_filter($payload, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<array-key, Address>  $addresses
     * @return list<array{email: string, name?: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_map($this->address(...), array_values($addresses));
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function address(Address $address): array
    {
        if ($address->getName() === '') {
            return ['email' => $address->getAddress()];
        }

        return ['email' => $address->getAddress(), 'name' => $address->getName()];
    }

    /**
     * @return list<array<string, bool|string>>
     */
    private function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $part) {
            $inline = $part->getDisposition() === 'inline';

            $attachment = [
                'file_name' => $part->getFilename() ?? $part->getContentId(),
                'content_type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'content_disposition' => $inline ? 'inline' : 'attachment',
                'base64' => true,
                'data' => base64_encode($part->getBody()),
            ];

            if ($inline) {
                // AhaSend only renders the part inline when the Content-ID
                // is wrapped in angle brackets.
                $attachment['content_id'] = '<'.$part->getContentId().'>';
            }

            $attachments[] = $attachment;
        }

        return $attachments;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $header) {
            if (! $header instanceof HeaderInterface || $header instanceof TagHeader) {
                continue;
            }

            if (in_array(strtolower($header->getName()), self::SKIPPED_HEADERS, true)) {
                continue;
            }

            if ($this->isControlHeader($header->getName())) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }

    /**
     * @return list<string>
     */
    private function tags(Email $email): array
    {
        $tags = [];

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tags[] = $header->getValue();
            }
        }

        return $tags;
    }

    /**
     * @return array{open?: bool, click?: bool}
     */
    private function tracking(Email $email): array
    {
        return array_filter([
            'open' => $this->boolean($email, self::HEADER_TRACK_OPENS) ?? ($this->options['track_opens'] ?? null),
            'click' => $this->boolean($email, self::HEADER_TRACK_CLICKS) ?? ($this->options['track_clicks'] ?? null),
        ], static fn (?bool $value): bool => $value !== null);
    }

    /**
     * @return array{first_attempt?: string, expires?: string}
     */
    private function schedule(Email $email): array
    {
        return array_filter([
            'first_attempt' => $this->header($email, self::HEADER_SCHEDULED_AT),
            'expires' => $this->header($email, self::HEADER_EXPIRES_AT),
        ], static fn (?string $value): bool => $value !== null);
    }

    private function header(Email $email, string $name): ?string
    {
        $value = $email->getHeaders()->get($name)?->getBodyAsString();

        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function boolean(Email $email, string $name): ?bool
    {
        $value = $this->header($email, $name);

        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function isControlHeader(string $name): bool
    {
        foreach (self::CONTROL_HEADERS as $control) {
            if (strcasecmp($control, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
