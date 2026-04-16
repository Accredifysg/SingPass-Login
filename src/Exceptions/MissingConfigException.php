<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Exceptions;

use RuntimeException;

class MissingConfigException extends RuntimeException
{
    public function __construct(string $configKey)
    {
        parent::__construct(
            "Required config value '{$configKey}' is not set. "
            .'Please ensure the corresponding environment variable is defined.'
        );
    }
}
