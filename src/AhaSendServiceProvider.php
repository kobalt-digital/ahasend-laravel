<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;
use KobaltDigital\AhaSend\Http\Middleware\VerifyWebhookSignature;
use KobaltDigital\AhaSend\Transport\AhaSendTransport;
use KobaltDigital\AhaSend\Webhooks\SignatureVerifier;
use Psr\Log\LoggerInterface;

final class AhaSendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ahasend.php', 'ahasend');

        $this->registerClient();
        $this->registerSignatureVerifier();
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerTransport();
        $this->registerRoutes();
    }

    private function registerClient(): void
    {
        $this->app->singleton(AhaSend::class, static function (Application $app): AhaSend {
            $config = $app->make(Repository::class);

            $apiKey = $config->get('ahasend.api_key') ?? $config->get('services.ahasend.key');
            $accountId = $config->get('ahasend.account_id') ?? $config->get('services.ahasend.account_id');

            if (! is_string($apiKey) || $apiKey === '') {
                throw InvalidConfiguration::apiKeyMissing();
            }

            if (! is_string($accountId) || $accountId === '') {
                throw InvalidConfiguration::accountIdMissing();
            }

            return new AhaSend(
                $app->make(Factory::class),
                $apiKey,
                $accountId,
                is_string($baseUrl = $config->get('ahasend.base_url')) ? $baseUrl : AhaSend::DEFAULT_BASE_URL,
                is_numeric($timeout = $config->get('ahasend.timeout')) ? (int) $timeout : 30,
                is_numeric($retries = $config->get('ahasend.retries')) ? (int) $retries : 2,
            );
        });

        $this->app->alias(AhaSend::class, 'ahasend');
    }

    private function registerSignatureVerifier(): void
    {
        $this->app->singleton(SignatureVerifier::class, static function (Application $app): SignatureVerifier {
            $config = $app->make(Repository::class);

            $secrets = $config->get('ahasend.webhook.secret');
            $secrets = is_string($secrets) ? explode(',', $secrets) : (array) $secrets;
            $secrets = array_map(static fn (mixed $secret): string => is_string($secret) ? trim($secret) : '', $secrets);

            $tolerance = $config->get('ahasend.webhook.tolerance');

            return new SignatureVerifier(
                $secrets,
                is_numeric($tolerance) ? (int) $tolerance : SignatureVerifier::DEFAULT_TOLERANCE,
            );
        });
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/ahasend.php' => $this->app->configPath('ahasend.php'),
        ], 'ahasend-config');
    }

    private function registerTransport(): void
    {
        $this->callAfterResolving('mail.manager', function (MailManager $manager): void {
            $manager->extend('ahasend', function (array $config = []): AhaSendTransport {
                /** @var array{sandbox?: bool, track_opens?: bool|null, track_clicks?: bool|null} $options */
                $options = array_intersect_key($config, array_flip(['sandbox', 'track_opens', 'track_clicks']));

                return new AhaSendTransport(
                    $this->app->make(AhaSend::class),
                    $options,
                    logger: $this->app->make(LoggerInterface::class),
                );
            });
        });
    }

    private function registerRoutes(): void
    {
        $config = $this->app->make(Repository::class);

        if (! $config->get('ahasend.routes', true)) {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = (array) $config->get('ahasend.middleware', []);

        Route::group([
            'domain' => $config->get('ahasend.domain'),
            'prefix' => $config->get('ahasend.path', 'ahasend'),
            'as' => 'ahasend.',
            'middleware' => [...$middleware, VerifyWebhookSignature::class],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        });
    }
}
