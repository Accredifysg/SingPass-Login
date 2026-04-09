<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Http\Controllers\GetSingPassCallbackController;
use Accredifysg\SingPassLogin\Interfaces\DPoPServiceInterface;
use Accredifysg\SingPassLogin\SingPassLogin;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use PHPUnit\Framework\MockObject\MockObject;

class GetSingPassCallbackControllerTest extends TestCase
{
    private JWK $dpopKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dpopKey = JWKFactory::createECKey('P-256');
    }

    public function test_invoke_calls_handle_callback_and_redirects(): void
    {
        /** @var MockObject&SingPassLogin $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->once())
            ->method('handleCallback')
            ->with('test-code', 'test-state', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback');

        /** @var MockObject&DPoPServiceInterface $dpopServiceMock */
        $dpopServiceMock = $this->createMock(DPoPServiceInterface::class);
        $dpopServiceMock->expects($this->once())
            ->method('retrieveKeyForState')
            ->with('test-state')
            ->willReturn($this->dpopKey);
        $dpopServiceMock->expects($this->once())
            ->method('clearKeyForState')
            ->with('test-state');

        $redirectMock = $this->createMock(RedirectResponse::class);
        Redirect::shouldReceive('intended')->once()->andReturn($redirectMock);

        $controller = new GetSingPassCallbackController;

        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        // Store auth context in session
        session()->put('auth_state_test-state', true);
        session()->put('code_verifier_test-state', 'test-code-verifier');
        session()->put('auth_client_id_test-state', 'test-client-id');
        session()->put('auth_redirect_uri_test-state', 'https://example.com/callback');

        $response = $controller->__invoke($request, $singPassLoginMock, $dpopServiceMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function test_sing_pass_login_exception_render(): void
    {
        Route::get('/login')->name('login');

        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->once())
            ->method('handleCallback')
            ->willThrowException(new SingPassLoginException);

        /** @var MockObject&DPoPServiceInterface $dpopServiceMock */
        $dpopServiceMock = $this->createMock(DPoPServiceInterface::class);
        $dpopServiceMock->expects($this->once())
            ->method('retrieveKeyForState')
            ->with('test-state')
            ->willReturn($this->dpopKey);

        $controller = new GetSingPassCallbackController;

        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);
        session()->put('auth_state_test-state', true);
        session()->put('code_verifier_test-state', 'test-code-verifier');
        session()->put('auth_client_id_test-state', 'test-client-id');
        session()->put('auth_redirect_uri_test-state', 'https://example.com/callback');

        $response = $controller->__invoke($request, $singPassLoginMock, $dpopServiceMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());

        $this->assertEquals([
            'singpass' => [
                [
                    'title' => 'No Account Found',
                    'description' => 'This SingPass account is not connected with any existing accounts in our system.',
                ],
            ],
        ], session('errors')->getBag('default')->messages());
    }

    public function test_missing_required_parameters_throws_exception(): void
    {
        Route::get('/login')->name('login');

        $controller = new GetSingPassCallbackController;

        // Create the request with missing parameters
        $request = new Request([], [], [], []);

        /** @var MockObject&SingPassLogin $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->never())->method('handleCallback');

        /** @var MockObject&DPoPServiceInterface $dpopServiceMock */
        $dpopServiceMock = $this->createMock(DPoPServiceInterface::class);

        $response = $controller->__invoke($request, $singPassLoginMock, $dpopServiceMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());

        $this->assertEquals([
            'singpass' => [
                [
                    'title' => 'Request Error',
                    'description' => 'An error has occurred when processing your request.',
                ],
            ],
        ], session('errors')->getBag('default')->messages());
    }

    public function test_authentication_error_response(): void
    {
        Route::get('/login')->name('login');

        $controller = new GetSingPassCallbackController;

        $request = new Request([
            'error' => 'access_denied',
            'error_description' => 'The user denied the request',
            'state' => 'test-state',
        ]);

        /** @var MockObject&SingPassLogin $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->never())->method('handleCallback');

        /** @var MockObject&DPoPServiceInterface $dpopServiceMock */
        $dpopServiceMock = $this->createMock(DPoPServiceInterface::class);

        $response = $controller->__invoke($request, $singPassLoginMock, $dpopServiceMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());

        $errors = session('errors')->getBag('default')->messages();
        $this->assertEquals('Authentication Error', $errors['singpass'][0]['title']);
        $this->assertStringContainsString('The user denied the request', $errors['singpass'][0]['description']);
    }

    public function test_invalid_state_fails_csrf_check(): void
    {
        Route::get('/login')->name('login');

        $controller = new GetSingPassCallbackController;

        $request = new Request(['code' => 'test-code', 'state' => 'forged-state']);

        /** @var MockObject&SingPassLogin $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->never())->method('handleCallback');

        /** @var MockObject&DPoPServiceInterface $dpopServiceMock */
        $dpopServiceMock = $this->createMock(DPoPServiceInterface::class);
        $dpopServiceMock->expects($this->never())->method('retrieveKeyForState');

        $response = $controller->__invoke($request, $singPassLoginMock, $dpopServiceMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    public function test_missing_dpop_key_in_session_throws_exception(): void
    {
        Route::get('/login')->name('login');

        $controller = new GetSingPassCallbackController;

        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);
        session()->put('auth_state_test-state', true);

        /** @var MockObject&SingPassLogin $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->never())->method('handleCallback');

        /** @var MockObject&DPoPServiceInterface $dpopServiceMock */
        $dpopServiceMock = $this->createMock(DPoPServiceInterface::class);
        $dpopServiceMock->expects($this->once())
            ->method('retrieveKeyForState')
            ->with('test-state')
            ->willReturn(null);

        $response = $controller->__invoke($request, $singPassLoginMock, $dpopServiceMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    protected function getPackageProviders($app): array
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
