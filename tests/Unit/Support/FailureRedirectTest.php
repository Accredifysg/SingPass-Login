<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Support;

use Accredifysg\SingPassLogin\Support\FailureRedirect;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

class FailureRedirectTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $errors = [
        'singpass' => [
            [
                'title' => 'No Account Found',
                'description' => 'Account is not connected.',
            ],
        ],
    ];

    public function test_falls_back_to_login_route_with_flashed_errors_when_url_not_configured(): void
    {
        config(['ndi.failure_redirect_url' => null]);
        Route::get('/login')->name('login');

        $response = FailureRedirect::make($this->errors, 'singpass_no_account', 'Account is not connected.');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('login'), $response->getTargetUrl());

        $errors = session('errors');
        $this->assertInstanceOf(ViewErrorBag::class, $errors);
        $bag = $errors->getBag('default');
        $this->assertInstanceOf(MessageBag::class, $bag);
        $this->assertSame($this->errors, $bag->messages());
    }

    public function test_falls_back_to_login_route_when_url_is_empty_string(): void
    {
        config(['ndi.failure_redirect_url' => '']);
        Route::get('/login')->name('login');

        $response = FailureRedirect::make($this->errors, 'singpass_no_account');

        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    public function test_redirects_to_configured_url_with_error_query_parameters(): void
    {
        config(['ndi.failure_redirect_url' => 'https://app.example.com/login']);

        $response = FailureRedirect::make($this->errors, 'singpass_no_account', 'Account is not connected.');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            'https://app.example.com/login?error=singpass_no_account&error_description=Account+is+not+connected.',
            $response->getTargetUrl()
        );
    }

    public function test_configured_url_redirect_does_not_flash_session_errors(): void
    {
        config(['ndi.failure_redirect_url' => 'https://app.example.com/login']);

        FailureRedirect::make($this->errors, 'singpass_no_account', 'Account is not connected.');

        $this->assertNull(session('errors'));
    }

    public function test_appends_with_ampersand_when_configured_url_already_has_a_query_string(): void
    {
        config(['ndi.failure_redirect_url' => 'https://app.example.com/login?source=singpass']);

        $response = FailureRedirect::make($this->errors, 'auth_flow_error');

        $this->assertSame(
            'https://app.example.com/login?source=singpass&error=auth_flow_error',
            $response->getTargetUrl()
        );
    }

    public function test_omits_error_description_when_not_provided(): void
    {
        config(['ndi.failure_redirect_url' => 'https://app.example.com/login']);

        $response = FailureRedirect::make($this->errors, 'auth_flow_error', null);

        $this->assertSame(
            'https://app.example.com/login?error=auth_flow_error',
            $response->getTargetUrl()
        );
    }
}
