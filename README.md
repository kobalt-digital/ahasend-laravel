# AhaSend for Laravel

[![Tests](https://github.com/kobalt-digital/ahasend-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/kobalt-digital/ahasend-laravel/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/kobalt-digital/ahasend-laravel.svg)](https://packagist.org/packages/kobalt-digital/ahasend-laravel)
[![License](https://img.shields.io/packagist/l/kobalt-digital/ahasend-laravel.svg)](LICENSE)

Send mail from Laravel through [AhaSend](https://ahasend.com). You get a mail transport, a client for the rest of the API and a webhook endpoint that turns AhaSend events into Laravel events.

Requires PHP 8.2+ and Laravel 12 or 13.

## Installation

```bash
composer require kobalt-digital/ahasend-laravel
```

Add your API key and account ID to `.env`. Both are in the AhaSend dashboard.

```ini
MAIL_MAILER=ahasend
AHASEND_API_KEY=aha-sk-...
AHASEND_ACCOUNT_ID=...
```

Then add the mailer to `config/mail.php`:

```php
'mailers' => [
    'ahasend' => [
        'transport' => 'ahasend',
    ],
],
```

That's it. Mailables and notifications now go out through AhaSend.

## Sending mail

Nothing changes in how you write mail. To, cc, bcc, reply-to, attachments, inline images and tags all work.

A few AhaSend features are set per message with a header. They are stripped before the mail is sent.

```php
use KobaltDigital\AhaSend\Transport\AhaSendTransport;
use Illuminate\Mail\Mailables\Headers;

public function headers(): Headers
{
    return new Headers(text: [
        AhaSendTransport::HEADER_IDEMPOTENCY_KEY => "invoice-{$this->invoice->id}",
        AhaSendTransport::HEADER_SCHEDULED_AT => now()->addHour()->toRfc3339String(),
    ]);
}
```

| Header | Value |
|---|---|
| `X-AhaSend-Idempotency-Key` | Any string. Sending the same key twice sends one mail. |
| `X-AhaSend-Sandbox` | `true` to send to the [sandbox](https://ahasend.com/docs/send-api/sandbox). |
| `X-AhaSend-Sandbox-Result` | `deliver`, `bounce`, `defer`, `fail` or `suppress`. |
| `X-AhaSend-Track-Opens` | `true` or `false`. |
| `X-AhaSend-Track-Clicks` | `true` or `false`. |
| `X-AhaSend-Scheduled-At` | RFC 3339 time, at most 7 days ahead. |
| `X-AhaSend-Expires-At` | RFC 3339 time after which the mail is dropped. |

Sandbox and tracking can also be set for the whole mailer:

```php
'ahasend' => [
    'transport' => 'ahasend',
    'sandbox' => env('AHASEND_SANDBOX', false),
    'track_opens' => false,
    'track_clicks' => false,
],
```

After sending, the AhaSend message ID is on the message as `X-AhaSend-Message-ID`. Listen for Laravel's `MessageSent` event if you want to store it.

## The API

For everything else there is a facade. Paths are relative to your account.

```php
use KobaltDigital\AhaSend\Facades\AhaSend;

AhaSend::messages()->send([
    'from' => ['email' => 'hello@example.com'],
    'recipients' => [
        ['email' => 'ada@example.org', 'substitutions' => ['name' => 'Ada']],
    ],
    'subject' => 'Welcome, {{ name }}',
    'html_content' => '<p>Hello {{ name }}</p>',
]);

AhaSend::messages()->get($id);
AhaSend::messages()->cancel($id);

AhaSend::get('domains');
AhaSend::ping();
```

Requests that hit a rate limit, a server error or a dropped connection are retried twice. Every `POST` carries an idempotency key, so a retry never sends a mail twice. A request that still fails throws an `ApiException` (with `status` and `body`) or a `ConnectionFailed`.

See the [AhaSend API reference](https://ahasend.com/docs/api-reference) for all endpoints and payloads.

## Webhooks

Create a webhook in AhaSend that points to `https://your-app.com/ahasend/webhook` and put its secret in `.env`:

```ini
AHASEND_WEBHOOK_SECRET=aha-whsec-...
```

Using more than one webhook or an inbound route? Separate the secrets with a comma.

Every delivery is verified before anything happens. Then listen for the event you need:

```php
use KobaltDigital\AhaSend\Events\MessageBounced;

Event::listen(function (MessageBounced $event) {
    $email = $event->data('recipient');
    $reason = $event->data('delivery_attempt.response');

    // ...
});
```

Each [AhaSend event](https://ahasend.com/docs/api-reference/webhooks) has a class in `KobaltDigital\AhaSend\Events` with the same name: `message.delivered` is `MessageDelivered`, `contact.added_to_list` is `ContactAddedToList`. Inbound mail arrives as `MessageRouting`. `WebhookReceived` fires for every delivery, including event types added after this release.

Two things to keep in mind:

- AhaSend retries deliveries, so you can get an event more than once. `$event->deliveryId()` is the same for each retry.
- Events don't arrive in order. Sort on `$event->occurredAt()`.

## Configuration

Publish the config file if you want to change the timeout, the retries or the webhook route:

```bash
php artisan vendor:publish --tag=ahasend-config
```

## Security

- The API key is only sent over HTTPS and never ends up in an exception message.
- Webhooks without a valid signature and a recent timestamp get a 403. There is no way to turn verification off.
- No webhook secret configured means the endpoint returns a 500, not a 200.

Found a problem? See [SECURITY.md](SECURITY.md).

## Testing

```bash
composer test
```

## Credits

Built by [Kobalt](https://kobaltdigital.nl). Not affiliated with AhaSend.

## License

MIT. See [LICENSE](LICENSE).
