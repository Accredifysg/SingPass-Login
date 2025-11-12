<?php

namespace Accredifysg\SingPassLogin\Exceptions;

use Exception;

class UserInfoDecryptionException extends SingPassLoginException
{
    public function __construct(int $statusCode = 500, string $message = 'Failed to decrypt UserInfo JWE token.', ?Exception $previous = null, array $headers = [], int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }
}
