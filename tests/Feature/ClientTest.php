<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use KobaltDigital\AhaSend\AhaSend;
use KobaltDigital\AhaSend\Exceptions\ApiException;
use KobaltDigital\AhaSend\Exceptions\ConnectionFailed;
use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;
use KobaltDigital\AhaSend\Tests\TestCase;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();

    $this->client = app(AhaSend::class);
});

it('authenticates with the api key', function (): void {
    Http::fake(['*' => Http::response(['message' => 'pong'])]);

    expect($this->client->ping())->toBe(['message' => 'pong']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.ahasend.com/v2/ping'
        && $request->hasHeader('Authorization', 'Bearer '.TestCase::API_KEY)
        && $request->hasHeader('Accept', 'application/json')
        && $request->hasHeader('User-Agent', 'kobaltdigital/ahasend-laravel'));
});

it('scopes requests to the account', function (): void {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    $this->client->get('domains', ['limit' => 10]);
    $this->client->get('/domains/example.com');

    Http::assertSentInOrder([
        fn (Request $request): bool => $request->url() === endpoint('domains').'?limit=10',
        fn (Request $request): bool => $request->url() === endpoint('domains/example.com'),
    ]);
});

it('sends a json body', function (string $method): void {
    Http::fake(['*' => Http::response(['id' => 'abc'])]);

    expect($this->client->{$method}('webhooks', ['name' => 'Laravel']))->toBe(['id' => 'abc']);

    Http::assertSent(fn (Request $request): bool => $request->method() === strtoupper($method)
        && $request->url() === endpoint('webhooks')
        && $request->isJson()
        && $request->data() === ['name' => 'Laravel']);
})->with(['post', 'put']);

it('sends a delete request', function (): void {
    Http::fake(['*' => Http::response(['message' => 'deleted'])]);

    $this->client->delete('suppressions', ['email' => 'ada@example.org']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === endpoint('suppressions').'?email=ada%40example.org');
});

it('adds an idempotency key to every post request', function (): void {
    Http::fake(['*' => Http::response([])]);

    $this->client->post('domains', ['domain' => 'example.com']);

    Http::assertSent(fn (Request $request): bool => Str::isUuid($request->header('Idempotency-Key')[0]));
});

it('uses the given idempotency key', function (): void {
    Http::fake(['*' => Http::response([])]);

    $this->client->post('domains', ['domain' => 'example.com'], 'create-example-com');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', 'create-example-com'));
});

it('does not add an idempotency key to other requests', function (string $method): void {
    Http::fake(['*' => Http::response([])]);

    $this->client->{$method}('domains/example.com');

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Idempotency-Key'));
})->with(['get', 'put', 'delete']);

it('returns an empty array for a response without a json body', function (): void {
    Http::fake(['*' => Http::response('', 204)]);

    expect($this->client->delete('domains/example.com'))->toBe([]);
});

it('throws when the api returns an error', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Invalid API key'], 401)]);

    expect(fn () => $this->client->ping())->toThrow(function (ApiException $exception): void {
        expect($exception->status)->toBe(401)
            ->and($exception->getMessage())->toContain('Invalid API key');
    });

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('does not retry a request the api refused', function (int $status): void {
    Http::fake(['*' => Http::response(['message' => 'nope'], $status)]);

    expect(fn () => $this->client->get('messages'))->toThrow(ApiException::class);

    Http::assertSentCount(1);
})->with([400, 401, 403, 404, 409, 422]);

it('retries after a rate limit or a server error', function (int $status): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'try again'], $status)
        ->push(['message' => 'try again'], $status)
        ->push(['message' => 'pong']),
    ]);

    expect($this->client->ping())->toBe(['message' => 'pong']);

    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::for(250)->milliseconds(), Sleep::for(500)->milliseconds()]);
})->with([429, 500, 502, 503, 504]);

it('retries with the same idempotency key', function (): void {
    Http::fake(['*' => Http::sequence()->push([], 503)->push(['id' => 'abc'], 202)]);

    $this->client->post('messages', ['subject' => 'Hello']);

    $keys = Http::recorded()->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0]);

    expect($keys)->toHaveCount(2)
        ->and($keys->unique())->toHaveCount(1);
});

it('waits as long as the retry-after header asks', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['message' => 'Too many requests, rate limited.'], 429, ['Retry-After' => '3'])
        ->push(['message' => 'pong']),
    ]);

    $this->client->ping();

    Sleep::assertSequence([Sleep::for(3000)->milliseconds()]);
});

it('never waits longer than thirty seconds', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push([], 503, ['Retry-After' => '3600'])
        ->push(['message' => 'pong']),
    ]);

    $this->client->ping();

    Sleep::assertSequence([Sleep::for(30000)->milliseconds()]);
});

it('gives up after the configured number of retries', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Internal server error'], 500)]);

    expect(fn () => $this->client->ping())->toThrow(function (ApiException $exception): void {
        expect($exception->status)->toBe(500);
    });

    Http::assertSentCount(3);
});

it('does not retry when retries are turned off', function (): void {
    config()->set('ahasend.retries', 0);
    app()->forgetInstance(AhaSend::class);

    Http::fake(['*' => Http::response([], 500)]);

    expect(fn () => app(AhaSend::class)->ping())->toThrow(ApiException::class);

    Http::assertSentCount(1);
});

it('retries when the api cannot be reached', function (): void {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        if (++$attempts === 1) {
            throw new ConnectionException('cURL error 7: Failed to connect');
        }

        return Http::response(['message' => 'pong']);
    });

    expect($this->client->ping())->toBe(['message' => 'pong'])
        ->and($attempts)->toBe(2);
});

it('throws when the api stays unreachable', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => $this->client->ping())->toThrow(
        ConnectionFailed::class,
        'Could not connect to the AhaSend API: cURL error 28: Operation timed out',
    );
});

it('does not retry on an unexpected exception', function (): void {
    $attempts = 0;

    Http::fake(function () use (&$attempts): never {
        $attempts++;

        throw new LogicException('Something else broke');
    });

    expect(fn () => $this->client->ping())->toThrow(LogicException::class)
        ->and($attempts)->toBe(1);
});

it('talks to another base url', function (): void {
    config()->set('ahasend.base_url', 'https://ahasend.test/');
    app()->forgetInstance(AhaSend::class);

    Http::fake(['*' => Http::response([])]);

    app(AhaSend::class)->get('domains');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ahasend.test/v2/accounts/'.TestCase::ACCOUNT_ID.'/domains');
});

it('refuses to send the api key over plain http', function (string $baseUrl): void {
    config()->set('ahasend.base_url', $baseUrl);
    app()->forgetInstance(AhaSend::class);

    app(AhaSend::class);
})->with(['http://api.ahasend.com', 'api.ahasend.com', 'ftp://api.ahasend.com', ''])
    ->throws(InvalidConfiguration::class, 'must use HTTPS');

it('does not put the api key in the error', function (): void {
    config()->set('ahasend.base_url', 'http://ahasend.test');
    app()->forgetInstance(AhaSend::class);

    expect(fn () => app(AhaSend::class))->toThrow(function (InvalidConfiguration $exception): void {
        expect($exception->getMessage())->not->toContain(TestCase::API_KEY)
            ->and($exception->getTraceAsString())->not->toContain(TestCase::API_KEY);
    });
});

it('does not follow redirects and gives up after the timeout', function (): void {
    $seen = [];

    Http::fake(function (Request $request, array $options) use (&$seen) {
        $seen = $options;

        return Http::response([]);
    });

    $this->client->ping();

    expect($seen['allow_redirects'])->toBeFalse()
        ->and($seen['timeout'])->toBe(30);
});
