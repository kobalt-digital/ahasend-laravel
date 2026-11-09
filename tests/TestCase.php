<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use KobaltDigital\AhaSend\AhaSendServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const API_KEY = 'aha-sk-test';

    public const ACCOUNT_ID = '4cdd7bdd-294e-4762-892f-83d40abf5a87';

    public const WEBHOOK_SECRET = 'aha-whsec-test';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [AhaSendServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        tap($app->make(Repository::class), function (Repository $config): void {
            $config->set('ahasend.api_key', self::API_KEY);
            $config->set('ahasend.account_id', self::ACCOUNT_ID);
            $config->set('ahasend.webhook.secret', self::WEBHOOK_SECRET);
            $config->set('mail.default', 'ahasend');
            $config->set('mail.mailers.ahasend', ['transport' => 'ahasend']);
            $config->set('mail.from', ['address' => 'hello@example.com', 'name' => 'Example']);
        });
    }
}
