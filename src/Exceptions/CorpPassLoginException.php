<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Exceptions;

use Accredifysg\SingPassLogin\Support\FailureRedirect;
use Exception;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CorpPassLoginException extends HttpException
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(int $statusCode = 400, string $message = 'This CorpPass account is not connected with any existing accounts in our system.', ?Exception $previous = null, array $headers = [], int $code = 0)
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
                'corppass' => [
                    [
                        'title' => 'No Account Found',
                        'description' => $this->message,
                    ],
                ],
            ],
            'corppass_no_account',
            $this->message,
        );
    }
}
