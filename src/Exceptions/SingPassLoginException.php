<?php

namespace Accredifysg\SingPassLogin\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SingPassLoginException extends HttpException
{
    public function __construct(int $statusCode = 400, string $message = 'User not found.', ?Exception $previous = null, array $headers = [], int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(): RedirectResponse
    {
        return redirect()->back()->withErrors(
            [
                'singpass' => [
                    [
                        'title' => 'SingPass Login Error',
                        'description' => $this->message,
                    ],
                ],
            ]
        );
    }
}
