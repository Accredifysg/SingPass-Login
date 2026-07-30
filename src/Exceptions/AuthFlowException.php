<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Exceptions;

use Accredifysg\SingPassLogin\Support\FailureRedirect;
use Exception;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthFlowException extends HttpException
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(int $statusCode = 400, string $message = 'An error has occurred when processing your request.', ?Exception $previous = null, array $headers = [], int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(): RedirectResponse
    {
        return FailureRedirect::make(
            [
                'singpass' => [
                    [
                        'title' => 'Request Error',
                        'description' => $this->message,
                    ],
                ],
            ],
            'auth_flow_error',
            $this->message,
        );
    }
}
