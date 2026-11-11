<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use KobaltDigital\AhaSend\AhaSend;
use KobaltDigital\AhaSend\AhaSendServiceProvider;
use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;
use KobaltDigital\AhaSend\Facades\AhaSend as AhaSendFacade;
use KobaltDigital\AhaSend\Http\Middleware\VerifyWebhookSignature;
use KobaltDigital\AhaSend\Tests\TestCase;
use KobaltDigital\AhaSend\Transport\AhaSendTransport;

/**
 * Boot the provider again, on a clean router, with the current config.
 */
function rebootProvider(): Router
{
    $router = new Router(app('events'), app());

    app()->instance('router', $router);
    Route::clearResolvedInstance('router');

    (new AhaSendServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();

    return $router;
}

it('binds the client as a singleton', function (): void {
    expect(app(AhaSend::class))->toBeInstanceOf(AhaSend::class)
        ->toBe(app(AhaSend::class))
        ->toBe(app('ahasend'));
});

it('resolves the client behind the facade', function (): void {
    expect(AhaSendFacade::getFacadeRoot())->toBe(app(AhaSend::class));
});

it('registers the facade alias and the provider for discovery', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['laravel'])->toBe([
        'providers' => [AhaSendServiceProvider::class],
        'aliases' => ['AhaSend' => AhaSendFacade::class],
    ]);
});

it('requires an api key', function (mixed $apiKey): void {
    config()->set('ahasend.api_key', $apiKey);

    app(AhaSend::class);
})->with([null, '', 42])->throws(InvalidConfiguration::class, 'AHASEND_API_KEY');

it('requires an account id', function (mixed $accountId): void {
    config()->set('ahasend.account_id', $accountId);

    app(AhaSend::class);
})->with([null, '', 42])->throws(InvalidConfiguration::class, 'AHASEND_ACCOUNT_ID');

it('falls back to the services config', function (): void {
    config()->set('ahasend.api_key', null);
    config()->set('ahasend.account_id', null);
    config()->set('services.ahasend', ['key' => 'aha-sk-services', 'account_id' => 'services-account']);

    Http::fake(['*' => Http::response([])]);

    app(AhaSend::class)->get('domains');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer aha-sk-services')
        && $request->url() === 'https://api.ahasend.com/v2/accounts/services-account/domains');
});

it('falls back to sane defaults for broken http settings', function (): void {
    config()->set('ahasend.base_url', null);
    config()->set('ahasend.timeout', 'slow');
    config()->set('ahasend.retries', null);

    Http::fake(['*' => Http::response([], 500)]);

    rescue(fn () => app(AhaSend::class)->ping(), report: false);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.ahasend.com/v2/ping');
});

it('does not need credentials until the client is used', function (): void {
    config()->set('ahasend.api_key', null);

    expect(fn () => rebootProvider())->not->toThrow(InvalidConfiguration::class);
});

it('registers the mail transport', function (): void {
    expect(Mail::mailer('ahasend')->getSymfonyTransport())->toBeInstanceOf(AhaSendTransport::class);
});

it('registers the mail transport when the mailer was resolved before booting', function (): void {
    app()->forgetInstance('mail.manager');
    Mail::clearResolvedInstance('mail.manager');

    app('mail.manager');
    rebootProvider();

    expect(app('mail.manager')->mailer('ahasend')->getSymfonyTransport())->toBeInstanceOf(AhaSendTransport::class);
});

it('registers the webhook route', function (): void {
    $route = Route::getRoutes()->getByName('ahasend.webhook');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('ahasend/webhook')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->getDomain())->toBeNull()
        ->and($route->gatherMiddleware())->toBe([VerifyWebhookSignature::class]);
});

it('registers the webhook route on another path and domain', function (): void {
    config()->set('ahasend.path', 'hooks/mail');
    config()->set('ahasend.domain', 'hooks.example.com');
    config()->set('ahasend.middleware', ['throttle:60,1']);

    $route = rebootProvider()->getRoutes()->getByName('ahasend.webhook');

    expect($route->uri())->toBe('hooks/mail/webhook')
        ->and($route->getDomain())->toBe('hooks.example.com')
        ->and($route->gatherMiddleware())->toBe(['throttle:60,1', VerifyWebhookSignature::class]);
});

it('handles webhooks on the configured path', function (): void {
    config()->set('ahasend.path', 'hooks/mail');

    rebootProvider();
    Event::fake();

    postWebhook(payload('message-bounced'), uri: '/hooks/mail/webhook')->assertOk();
});

it('does not register the webhook route when routes are turned off', function (): void {
    config()->set('ahasend.routes', false);

    expect(rebootProvider()->getRoutes()->getByName('ahasend.webhook'))->toBeNull();
});

it('publishes the config file', function (): void {
    $paths = ServiceProvider::pathsToPublish(AhaSendServiceProvider::class, 'ahasend-config');

    expect($paths)->toHaveCount(1)
        ->and(realpath((string) array_key_first($paths)))->toBe(realpath(__DIR__.'/../../config/ahasend.php'))
        ->and(array_values($paths)[0])->toBe(config_path('ahasend.php'));
});

it('does not publish anything outside of the console', function (): void {
    ServiceProvider::$publishes = [];
    ServiceProvider::$publishGroups = [];

    $app = Mockery::mock(Application::class)->makePartial();
    $app->shouldReceive('runningInConsole')->andReturnFalse();

    $provider = new AhaSendServiceProvider($app);
    (fn () => $this->registerPublishing())->call($provider);

    expect(ServiceProvider::pathsToPublish(AhaSendServiceProvider::class))->toBe([]);
});

it('ships a config file with every documented key', function (): void {
    expect(config('ahasend'))->toHaveKeys([
        'api_key', 'account_id', 'base_url', 'timeout', 'retries',
        'webhook.secret', 'webhook.tolerance', 'routes', 'domain', 'path', 'middleware',
    ])
        ->and(config('ahasend.base_url'))->toBe('https://api.ahasend.com')
        ->and(config('ahasend.timeout'))->toBe(30)
        ->and(config('ahasend.retries'))->toBe(2)
        ->and(config('ahasend.webhook.tolerance'))->toBe(300)
        ->and(config('ahasend.routes'))->toBeTrue()
        ->and(config('ahasend.path'))->toBe('ahasend')
        ->and(config('ahasend.api_key'))->toBe(TestCase::API_KEY);
});
