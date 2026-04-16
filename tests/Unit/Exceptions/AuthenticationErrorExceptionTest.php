<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\AuthenticationErrorException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationErrorExceptionTest extends TestCase
{
    public function test_exception_inheritance(): void
    {
        $exception = new AuthenticationErrorException('access_denied', 'User cancelled');
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function test_constructor_with_description_uses_description_as_message(): void
    {
        $exception = new AuthenticationErrorException('access_denied', 'User cancelled the flow', 400);

        $this->assertSame('access_denied', $exception->getErrorCode());
        $this->assertSame('User cancelled the flow', $exception->getErrorDescription());
        $this->assertSame('User cancelled the flow', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
    }

    public function test_constructor_without_description_builds_default_message(): void
    {
        $exception = new AuthenticationErrorException('invalid_request');

        $this->assertSame('invalid_request', $exception->getErrorCode());
        $this->assertNull($exception->getErrorDescription());
        $this->assertSame('Authentication error: invalid_request', $exception->getMessage());
    }

    public function test_constructor_accepts_custom_status_code(): void
    {
        $exception = new AuthenticationErrorException('server_error', 'Upstream failure', 503);

        $this->assertSame(503, $exception->getStatusCode());
    }

    public function test_render_redirects_with_full_error_payload(): void
    {
        Route::get('/login')->name('login');

        $exception = new AuthenticationErrorException('access_denied', 'Consent was not granted');

        $response = $exception->render();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('login'), $response->getTargetUrl());

        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $bag = $errors->getBag('default');
        $this->assertInstanceOf(MessageBag::class, $bag);
        $this->assertSame([
            'singpass' => [
                [
                    'title' => 'Authentication Error',
                    'description' => 'Consent was not granted',
                    'error_code' => 'access_denied',
                ],
            ],
        ], $bag->messages());
    }

    public function test_render_uses_fallback_description_when_none_given(): void
    {
        Route::get('/login')->name('login');

        $exception = new AuthenticationErrorException('login_required');

        $response = $exception->render();

        $this->assertInstanceOf(RedirectResponse::class, $response);

        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $bag = $errors->getBag('default');
        $this->assertInstanceOf(MessageBag::class, $bag);
        $this->assertSame([
            'singpass' => [
                [
                    'title' => 'Authentication Error',
                    'description' => 'An error occurred during authentication: login_required',
                    'error_code' => 'login_required',
                ],
            ],
        ], $bag->messages());
    }
}
