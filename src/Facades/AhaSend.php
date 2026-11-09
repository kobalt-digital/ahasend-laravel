<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Facades;

use Illuminate\Support\Facades\Facade;
use KobaltDigital\AhaSend\AhaSend as Client;
use KobaltDigital\AhaSend\Resources\Messages;

/**
 * @method static Messages messages()
 * @method static array<array-key, mixed> ping()
 * @method static array<array-key, mixed> get(string $path, array<string, mixed> $query = [])
 * @method static array<array-key, mixed> post(string $path, array<string, mixed> $payload = [], ?string $idempotencyKey = null)
 * @method static array<array-key, mixed> put(string $path, array<string, mixed> $payload = [])
 * @method static array<array-key, mixed> delete(string $path, array<string, mixed> $query = [])
 *
 * @see Client
 */
final class AhaSend extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
