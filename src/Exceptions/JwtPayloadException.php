<?php

namespace Accredifysg\SingPassLogin\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JwtPayloadException extends HttpException
{
    public function __construct(int $statusCode = 500, string $message = 'The JWT Payload is invalid', Exception $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->message,
        ], $this->getStatusCode());
    }
}