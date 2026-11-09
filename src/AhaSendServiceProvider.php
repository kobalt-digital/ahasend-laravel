<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use KobaltDigital\AhaSend\Exceptions\InvalidConfiguration;

final class AhaSendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ahasend.php', 'ahasend');

        $this->registerClient();
    }

    public function boot(): void
    {
        $this->registerPublishing();
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

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/ahasend.php' => $this->app->configPath('ahasend.php'),
        ], 'ahasend-config');
    }
}
