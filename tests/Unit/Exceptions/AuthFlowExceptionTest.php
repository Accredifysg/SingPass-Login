<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthFlowExceptionTest extends TestCase
{
    public function test_exception_inheritance(): void
    {
        $exception = new AuthFlowException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function test_default_values(): void
    {
        $exception = new AuthFlowException;
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('An error has occurred when processing your request.', $exception->getMessage());
    }

    public function test_render(): void
    {
        Route::get('/login')->name('login');

        $exception = new AuthFlowException;

        $response = $exception->render();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $bag = $errors->getBag('default');
        $this->assertInstanceOf(MessageBag::class, $bag);
        $this->assertEquals([
            'singpass' => [
                [
                    'title' => 'Request Error',
                    'description' => 'An error has occurred when processing your request.',
                ],
            ],
        ], $bag->messages());
    }

    public function test_render_redirects_to_configured_failure_url(): void
    {
        config(['ndi.failure_redirect_url' => 'https://app.example.com/login']);

        $exception = new AuthFlowException;

        $response = $exception->render();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(
            'https://app.example.com/login?error=auth_flow_error&error_description='
                .urlencode('An error has occurred when processing your request.'),
            $response->getTargetUrl()
        );
    }
}
