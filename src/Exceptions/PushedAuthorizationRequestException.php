<?php

namespace Accredifysg\SingPassLogin\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PushedAuthorizationRequestException extends HttpException
{
    protected string $errorCode;

    protected ?string $errorDescription;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        int $statusCode = 500,
        string $message = 'Pushed Authorization Request failed',
        string $errorCode = 'server_error',
        ?string $errorDescription = null,
        ?Exception $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        $this->errorCode = $errorCode;
        $this->errorDescription = $errorDescription;
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorDescription(): ?string
    {
        return $this->errorDescription;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->errorCode,
            'error_description' => $this->errorDescription ?? $this->message,
        ], $this->getStatusCode());
    }
}
