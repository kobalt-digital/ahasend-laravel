<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KobaltDigital\AhaSend\Facades\AhaSend;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('sends a message', function (string $method, string $path): void {
    Http::fake(['*' => Http::response(payload('send-response'), 202)]);

    $payload = [
        'from' => ['email' => 'hello@example.com'],
        'recipients' => [['email' => 'ada@example.org']],
        'subject' => 'Welcome',
        'text_content' => 'Hello Ada',
    ];

    $response = AhaSend::messages()->{$method}($payload, 'welcome-ada');

    expect($response['data'][0]['id'])->toBe('0199f1c2-7d3a-7b4e-9a1f-2c3d4e5f6a7b');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === endpoint($path)
        && $request->data() === $payload
        && $request->hasHeader('Idempotency-Key', 'welcome-ada'));
})->with([
    'to separate recipients' => ['send', 'messages'],
    'as a conversation' => ['sendConversation', 'messages/conversation'],
    'from a template' => ['sendTemplate', 'messages/template'],
]);

it('gets a message', function (): void {
    Http::fake(['*' => Http::response(['object' => 'message', 'id' => 'abc'])]);

    expect(AhaSend::messages()->get('abc'))->toBe(['object' => 'message', 'id' => 'abc']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === endpoint('messages/abc'));
});

it('lists messages', function (): void {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    AhaSend::messages()->list(['status' => 'Bounced,Failed', 'limit' => 25]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === endpoint('messages').'?status=Bounced%2CFailed&limit=25');
});

it('lists messages without filters', function (): void {
    Http::fake(['*' => Http::response(['object' => 'list', 'data' => []])]);

    AhaSend::messages()->list();

    Http::assertSent(fn (Request $request): bool => $request->url() === endpoint('messages'));
});

it('cancels a scheduled message', function (): void {
    Http::fake(['*' => Http::response(['message' => 'Message cancelled'])]);

    AhaSend::messages()->cancel('abc');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === endpoint('messages/abc/cancel'));
});

it('encodes the message id', function (): void {
    Http::fake(['*' => Http::response([])]);

    AhaSend::messages()->get('../domains');

    Http::assertSent(fn (Request $request): bool => $request->url() === endpoint('messages/..%2Fdomains'));
});
