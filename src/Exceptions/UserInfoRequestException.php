<?php

namespace Accredifysg\SingPassLogin\Exceptions;

use Exception;

class UserInfoRequestException extends SingPassLoginException
{
    public function __construct(int $statusCode = 500, string $message = 'UserInfo endpoint request failed.', ?Exception $previous = null, array $headers = [], int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }
}
