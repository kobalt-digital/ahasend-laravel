<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Exceptions;

use Throwable;

/**
 * Thrown when the AhaSend API could not be reached at all.
 */
final class ConnectionFailed extends AhaSendException
{
    public static function from(Throwable $previous): self
    {
        return new self(
            sprintf('Could not connect to the AhaSend API: %s', $previous->getMessage()),
            0,
            $previous,
        );
    }
}
