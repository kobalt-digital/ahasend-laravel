<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Sleep;
use KobaltDigital\AhaSend\AhaSend;
use KobaltDigital\AhaSend\Transport\AhaSendTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();
});

/**
 * Send a mail through the AhaSend mailer and return what was sent.
 *
 * @param  Closure(Message): mixed  $build
 */
function sendMail(Closure $build, string $text = 'Hello Ada'): ?SentMessage
{
    // Stubs are matched in order, so a response a test faked earlier wins.
    Http::fake(['*' => Http::response(payload('send-response'), 202)]);

    return Mail::raw($text, function (Message $message) use ($build): void {
        $message->to('ada@example.org')->subject('Welcome');

        $build($message);
    });
}

/**
 * The JSON body of the one request that went to AhaSend.
 *
 * @return array<string, mixed>
 */
function sentPayload(): array
{
    Http::assertSentCount(1);

    return Http::recorded()->first()[0]->data();
}

it('sends mail through the conversation endpoint', function (): void {
    sendMail(fn (Message $message) => $message);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === endpoint('messages/conversation'));

    expect(sentPayload())->toBe([
        'from' => ['email' => 'hello@example.com', 'name' => 'Example'],
        'to' => [['email' => 'ada@example.org']],
        'subject' => 'Welcome',
        'text_content' => 'Hello Ada',
    ]);
});

it('sends the html and the text body', function (): void {
    Http::fake(['*' => Http::response(payload('send-response'), 202)]);

    Mail::send(
        ['html' => new HtmlString('<p>Hello <strong>Ada</strong></p>'), 'raw' => 'Hello Ada'],
        [],
        fn (Message $message) => $message->to('ada@example.org')->subject('Welcome'),
    );

    expect(sentPayload())
        ->toHaveKey('html_content', '<p>Hello <strong>Ada</strong></p>')
        ->toHaveKey('text_content', 'Hello Ada');
});

it('keeps to, cc and bcc recipients apart', function (): void {
    sendMail(fn (Message $message) => $message
        ->to('grace@example.org', 'Grace Hopper')
        ->cc('cc@example.org', 'Carbon Copy')
        ->bcc('archive@example.org'));

    expect(sentPayload())
        ->toHaveKey('to', [['email' => 'ada@example.org'], ['email' => 'grace@example.org', 'name' => 'Grace Hopper']])
        ->toHaveKey('cc', [['email' => 'cc@example.org', 'name' => 'Carbon Copy']])
        ->toHaveKey('bcc', [['email' => 'archive@example.org']]);
});

it('does not leak bcc recipients through the headers', function (): void {
    sendMail(fn (Message $message) => $message->bcc('archive@example.org'));

    expect(sentPayload())->not->toHaveKey('headers');
});

it('uses the from address of the message', function (): void {
    sendMail(fn (Message $message) => $message->from('billing@example.com', 'Billing'));

    expect(sentPayload())->toHaveKey('from', ['email' => 'billing@example.com', 'name' => 'Billing']);
});

it('sends the reply-to address', function (): void {
    sendMail(fn (Message $message) => $message->replyTo('support@example.com', 'Support'));

    expect(sentPayload())
        ->toHaveKey('reply_to', ['email' => 'support@example.com', 'name' => 'Support'])
        ->not->toHaveKey('headers');
});

it('sends attachments base64 encoded', function (): void {
    sendMail(fn (Message $message) => $message->attachData("id;total\n1;9,95\n", 'invoices.csv', ['mime' => 'text/csv']));

    expect(sentPayload())->toHaveKey('attachments', [[
        'file_name' => 'invoices.csv',
        'content_type' => 'text/csv',
        'content_disposition' => 'attachment',
        'base64' => true,
        'data' => base64_encode("id;total\n1;9,95\n"),
    ]]);
});

it('sends binary attachments unharmed', function (): void {
    $binary = random_bytes(512);

    sendMail(fn (Message $message) => $message->attachData($binary, 'blob.bin'));

    expect(base64_decode(sentPayload()['attachments'][0]['data'], true))->toBe($binary);
});

it('sends embedded images inline', function (): void {
    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==', true);
    $reference = null;

    sendMail(function (Message $message) use ($pixel, &$reference): void {
        $reference = $message->embedData($pixel, 'pixel.png', 'image/png');
    });

    $attachment = sentPayload()['attachments'][0];

    expect($reference)->toStartWith('cid:')
        ->and($attachment)
        ->toHaveKey('file_name', 'pixel.png')
        ->toHaveKey('content_type', 'image/png')
        ->toHaveKey('content_disposition', 'inline')
        ->toHaveKey('content_id', '<'.substr((string) $reference, 4).'>');
});

it('sends tags', function (): void {
    sendMail(fn (Message $message) => $message->getSymfonyMessage()->getHeaders()
        ->add(new TagHeader('welcome'))
        ->add(new TagHeader('onboarding')));

    expect(sentPayload())
        ->toHaveKey('tags', ['welcome', 'onboarding'])
        ->not->toHaveKey('headers');
});

it('sends metadata and custom headers as headers', function (): void {
    sendMail(function (Message $message): void {
        $headers = $message->getSymfonyMessage()->getHeaders();

        $headers->add(new MetadataHeader('order', '1234'));
        $headers->addTextHeader('X-Campaign', 'spring');
        $headers->addTextHeader('List-Unsubscribe', '<https://example.com/unsubscribe>');
    });

    expect(sentPayload())->toHaveKey('headers', [
        'X-Metadata-order' => '1234',
        'X-Campaign' => 'spring',
        'List-Unsubscribe' => '<https://example.com/unsubscribe>',
    ]);
});

it('leaves out headers that ahasend sets itself', function (): void {
    sendMail(function (Message $message): void {
        $message->sender('bounces@example.com')->returnPath('bounces@example.com');
        $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'custom@example.com');
    });

    expect(sentPayload())->not->toHaveKey('headers');
});

it('passes the idempotency key on', function (): void {
    sendMail(fn (Message $message) => $message->getSymfonyMessage()->getHeaders()
        ->addTextHeader(AhaSendTransport::HEADER_IDEMPOTENCY_KEY, 'welcome-ada'));

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', 'welcome-ada'));

    expect(sentPayload())->not->toHaveKey('headers');
});

it('sends to the sandbox when the message asks for it', function (): void {
    sendMail(function (Message $message): void {
        $headers = $message->getSymfonyMessage()->getHeaders();

        $headers->addTextHeader(AhaSendTransport::HEADER_SANDBOX, 'true');
        $headers->addTextHeader(AhaSendTransport::HEADER_SANDBOX_RESULT, 'bounce');
    });

    expect(sentPayload())
        ->toHaveKey('sandbox', true)
        ->toHaveKey('sandbox_result', 'bounce')
        ->not->toHaveKey('headers');
});

it('sends to the sandbox when the mailer is configured for it', function (): void {
    config()->set('mail.mailers.ahasend.sandbox', true);

    sendMail(fn (Message $message) => $message);

    expect(sentPayload())->toHaveKey('sandbox', true);
});

it('lets a message leave the sandbox of the mailer', function (): void {
    config()->set('mail.mailers.ahasend.sandbox', true);

    sendMail(fn (Message $message) => $message->getSymfonyMessage()->getHeaders()
        ->addTextHeader(AhaSendTransport::HEADER_SANDBOX, 'false'));

    expect(sentPayload())->not->toHaveKey('sandbox');
});

it('leaves tracking to the account by default', function (): void {
    sendMail(fn (Message $message) => $message);

    expect(sentPayload())->not->toHaveKey('tracking');
});

it('sets tracking for a single message', function (): void {
    sendMail(function (Message $message): void {
        $headers = $message->getSymfonyMessage()->getHeaders();

        $headers->addTextHeader(AhaSendTransport::HEADER_TRACK_OPENS, 'false');
        $headers->addTextHeader(AhaSendTransport::HEADER_TRACK_CLICKS, 'true');
    });

    expect(sentPayload())
        ->toHaveKey('tracking', ['open' => false, 'click' => true])
        ->not->toHaveKey('headers');
});

it('sets tracking for the whole mailer', function (): void {
    config()->set('mail.mailers.ahasend.track_opens', false);
    config()->set('mail.mailers.ahasend.track_clicks', false);

    sendMail(fn (Message $message) => $message->getSymfonyMessage()->getHeaders()
        ->addTextHeader(AhaSendTransport::HEADER_TRACK_CLICKS, 'true'));

    expect(sentPayload())->toHaveKey('tracking', ['open' => false, 'click' => true]);
});

it('schedules a message', function (): void {
    sendMail(function (Message $message): void {
        $headers = $message->getSymfonyMessage()->getHeaders();

        $headers->addTextHeader(AhaSendTransport::HEADER_SCHEDULED_AT, '2026-11-20T09:00:00Z');
        $headers->addTextHeader(AhaSendTransport::HEADER_EXPIRES_AT, '2026-11-21T09:00:00Z');
    });

    expect(sentPayload())
        ->toHaveKey('schedule', ['first_attempt' => '2026-11-20T09:00:00Z', 'expires' => '2026-11-21T09:00:00Z'])
        ->not->toHaveKey('headers');
});

it('recognises control headers regardless of their case', function (): void {
    sendMail(fn (Message $message) => $message->getSymfonyMessage()->getHeaders()
        ->addTextHeader('x-ahasend-sandbox', '1'));

    expect(sentPayload())
        ->toHaveKey('sandbox', true)
        ->not->toHaveKey('headers');
});

it('ignores an empty control header', function (): void {
    sendMail(fn (Message $message) => $message->getSymfonyMessage()->getHeaders()
        ->addTextHeader(AhaSendTransport::HEADER_SANDBOX_RESULT, ' '));

    expect(sentPayload())->not->toHaveKey('sandbox_result');
});

it('exposes the ids ahasend assigned', function (): void {
    $sent = sendMail(fn (Message $message) => $message);

    expect($sent?->getMessageId())->toBe('0199f1c2-7d3a-7b4e-9a1f-2c3d4e5f6a7b@example.com')
        ->and($sent?->getOriginalMessage()->getHeaders()->get(AhaSendTransport::HEADER_MESSAGE_ID)?->getBodyAsString())
        ->toBe('0199f1c2-7d3a-7b4e-9a1f-2c3d4e5f6a7b');
});

it('copes with a response without a message-id header', function (): void {
    $response = payload('send-response');
    unset($response['data'][0]['message_id_header']);

    Http::fake(['*' => Http::response($response, 202)]);

    $sent = sendMail(fn (Message $message) => $message);

    expect($sent?->getOriginalMessage()->getHeaders()->get(AhaSendTransport::HEADER_MESSAGE_ID)?->getBodyAsString())
        ->toBe('0199f1c2-7d3a-7b4e-9a1f-2c3d4e5f6a7b');
});

it('fails when the api refuses the message', function (): void {
    Http::fake(['*' => Http::response(['message' => "Sending from domain 'example.com' is paused."], 403)]);

    expect(fn () => sendMail(fn (Message $message) => $message))->toThrow(function (TransportException $exception): void {
        expect($exception->getMessage())
            ->toBe("Request to the AhaSend API failed. Reason: The AhaSend API responded with status 403: Sending from domain 'example.com' is paused.")
            ->and($exception->getCode())->toBe(403);
    });
});

it('fails when the api cannot be reached', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    sendMail(fn (Message $message) => $message);
})->throws(TransportException::class, 'Could not connect to the AhaSend API');

it('fails when no recipient was accepted', function (): void {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => [[
        'object' => 'message',
        'id' => null,
        'recipient' => ['email' => 'ada@example.org', 'name' => ''],
        'status' => 'error',
        'error' => 'recipient is suppressed',
    ]]], 202)]);

    sendMail(fn (Message $message) => $message);
})->throws(TransportException::class, 'AhaSend did not accept the message for any recipient. Reason: recipient is suppressed');

it('fails when the response holds no recipients', function (): void {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []], 202)]);

    sendMail(fn (Message $message) => $message);
})->throws(TransportException::class, 'Reason: Unknown error');

it('logs the recipients that were not accepted', function (): void {
    Log::spy();

    $response = payload('send-response');
    $response['data'][] = [
        'object' => 'message',
        'id' => null,
        'recipient' => ['email' => 'grace@example.org', 'name' => ''],
        'status' => 'error',
        'error' => 'recipient is suppressed',
    ];

    Http::fake(['*' => Http::response($response, 202)]);

    $sent = sendMail(fn (Message $message) => $message->to('grace@example.org'));

    expect($sent)->not->toBeNull();

    Log::shouldHaveReceived('warning')->once()->with('AhaSend did not accept the message for a recipient.', [
        'recipient' => 'grace@example.org',
        'error' => 'recipient is suppressed',
    ]);
});

it('refuses a message without headers', function (): void {
    $transport = new AhaSendTransport(app(AhaSend::class));

    $transport->send(
        new RawMessage("Subject: Welcome\r\n\r\nHello Ada"),
        new Envelope(new Address('hello@example.com'), [new Address('ada@example.org')]),
    );
})->throws(TransportException::class, 'can only send messages that carry headers and a body');

it('has a name', function (): void {
    expect((string) new AhaSendTransport(app(AhaSend::class)))->toBe('ahasend')
        ->and((string) Mail::mailer('ahasend')->getSymfonyTransport())->toBe('ahasend');
});
