<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationErrorException extends HttpException
{
    protected string $errorCode;

    protected ?string $errorDescription;

    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        string $errorCode,
        ?string $errorDescription = null,
        int $statusCode = 400,
        ?Exception $previous = null,
        array $headers = [],
        int $code = 0
    ) {
        $this->errorCode = $errorCode;
        $this->errorDescription = $errorDescription;
        parent::__construct($statusCode, $errorDescription ?? "Authentication error: {$errorCode}", $previous, $headers, $code);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorDescription(): ?string
    {
        return $this->errorDescription;
    }

    public function render(): RedirectResponse
    {
        return redirect()->route('login')->withErrors(
            [
                'singpass' => [
                    [
                        'title' => 'Authentication Error',
                        'description' => $this->errorDescription ?? "An error occurred during authentication: {$this->errorCode}",
                        'error_code' => $this->errorCode,
                    ],
                ],
            ]
        );
    }
}
