<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use KobaltDigital\AhaSend\Exceptions\ApiException;
use KobaltDigital\AhaSend\Exceptions\ConnectionFailed;
use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;

/**
 * @param  array<string, string>  $headers
 */
function apiResponse(int $status, string $body = '', array $headers = []): Response
{
    return new Response(new PsrResponse($status, $headers, $body));
}

it('describes the error the api returned', function (): void {
    $exception = ApiException::fromResponse(apiResponse(403, '{"message":"Insufficient permissions for the required scope"}'));

    expect($exception->getMessage())->toBe('The AhaSend API responded with status 403: Insufficient permissions for the required scope')
        ->and($exception->status)->toBe(403)
        ->and($exception->getCode())->toBe(403)
        ->and($exception->body)->toBe(['message' => 'Insufficient permissions for the required scope'])
        ->and($exception->retryAfter)->toBeNull()
        ->and($exception->isRateLimited())->toBeFalse()
        ->and($exception->isServerError())->toBeFalse();
});

it('copes with a response it cannot read', function (string $body): void {
    $exception = ApiException::fromResponse(apiResponse(502, $body));

    expect($exception->getMessage())->toBe('The AhaSend API responded with status 502: Unknown error')
        ->and($exception->isServerError())->toBeTrue();
})->with([
    'empty' => [''],
    'html' => ['<html><body>Bad Gateway</body></html>'],
    'a json string' => ['"nope"'],
    'no message' => ['{"error":"nope"}'],
    'an empty message' => ['{"message":""}'],
    'a message that is not a string' => ['{"message":{"nested":true}}'],
]);

it('keeps the retry-after delay', function (): void {
    $exception = ApiException::fromResponse(apiResponse(429, '{"message":"Too many requests, rate limited."}', ['Retry-After' => '7']));

    expect($exception->retryAfter)->toBe(7)
        ->and($exception->isRateLimited())->toBeTrue();
});

it('ignores a retry-after header that is not a number of seconds', function (): void {
    $exception = ApiException::fromResponse(apiResponse(503, '{}', ['Retry-After' => 'Wed, 21 Oct 2015 07:28:00 GMT']));

    expect($exception->retryAfter)->toBeNull();
});

it('wraps a connection failure', function (): void {
    $previous = new RuntimeException('cURL error 28: Operation timed out');
    $exception = ConnectionFailed::from($previous);

    expect($exception->getMessage())->toBe('Could not connect to the AhaSend API: cURL error 28: Operation timed out')
        ->and($exception->getPrevious())->toBe($previous);
});

it('tells which setting is missing', function (InvalidConfiguration $exception, string $variable): void {
    expect($exception->getMessage())->toContain($variable);
})->with([
    'api key' => [fn () => InvalidConfiguration::apiKeyMissing(), 'AHASEND_API_KEY'],
    'account id' => [fn () => InvalidConfiguration::accountIdMissing(), 'AHASEND_ACCOUNT_ID'],
    'webhook secret' => [fn () => InvalidConfiguration::webhookSecretMissing(), 'AHASEND_WEBHOOK_SECRET'],
]);
