<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\CorpPassLoginException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CorpPassLoginExceptionTest extends TestCase
{
    public function test_exception_inheritance(): void
    {
        $exception = new CorpPassLoginException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function test_default_values(): void
    {
        $exception = new CorpPassLoginException;
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('This CorpPass account is not connected with any existing accounts in our system.', $exception->getMessage());
    }

    public function test_render(): void
    {
        Route::get('/login')->name('login');

        $exception = new CorpPassLoginException;

        $response = $exception->render();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $bag = $errors->getBag('default');
        $this->assertInstanceOf(MessageBag::class, $bag);
        $this->assertEquals([
            'corppass' => [
                [
                    'title' => 'No Account Found',
                    'description' => 'This CorpPass account is not connected with any existing accounts in our system.',
                ],
            ],
        ], $bag->messages());
    }

    public function test_render_redirects_to_configured_failure_url(): void
    {
        config(['ndi.failure_redirect_url' => 'https://app.example.com/login']);

        $exception = new CorpPassLoginException;

        $response = $exception->render();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(
            'https://app.example.com/login?error=corppass_no_account&error_description='
                .urlencode('This CorpPass account is not connected with any existing accounts in our system.'),
            $response->getTargetUrl()
        );
    }
}
