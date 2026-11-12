<?php

declare(strict_types=1);

use KobaltDigital\AhaSend\Events\WebhookEvent;
use KobaltDigital\AhaSend\Exceptions\AhaSendException;

arch('the package does not ship debugging statements')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('every file declares strict types')
    ->expect('KobaltDigital\AhaSend')
    ->toUseStrictTypes();

arch('the package never reads the environment directly')
    ->expect('env')
    ->not->toBeUsedIn('KobaltDigital\AhaSend');

arch('exceptions extend the package exception')
    ->expect('KobaltDigital\AhaSend\Exceptions')
    ->classes()
    ->toExtend(AhaSendException::class);

arch('events extend the webhook event')
    ->expect('KobaltDigital\AhaSend\Events')
    ->classes()
    ->toExtend(WebhookEvent::class);

arch('enums are enums')
    ->expect('KobaltDigital\AhaSend\Enums')
    ->toBeEnums();
