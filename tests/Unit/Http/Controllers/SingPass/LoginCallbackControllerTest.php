<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\SingPass;

use Accredifysg\SingPassLogin\DTOs\FapiCallbackResult;
use Accredifysg\SingPassLogin\DTOs\FapiSessionContext;
use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\LoginCallbackController;
use Accredifysg\SingPassLogin\Services\FapiCallbackService;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;

class LoginCallbackControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SingPassLoginServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('singpass-login.client_id', 'test-client-id');
        Config::set('singpass-login.redirect_uri', 'https://example.com/callback');
        Config::set('singpass-login.discovery_endpoint', 'https://example.com/discovery');
        Config::set('singpass-login.domain', 'https://example.com');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_successful_login_fires_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: [
                'sub' => 'test-uuid',
                'sub_attributes' => ['identity_number' => 'S1234567A'],
            ],
            userInfoData: null,
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getNric() === 'S1234567A'
                && $event->getState() === 'test-state';
        });
    }

    public function test_auth_flow_exception_renders_redirect(): void
    {
        Route::get('/login')->name('login');

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')
            ->once()
            ->andThrow(new AuthFlowException);

        $controller = new LoginCallbackController;
        $request = new Request(['state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    public function test_singpass_login_exception_renders_redirect(): void
    {
        Route::get('/login')->name('login');

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')
            ->once()
            ->andThrow(new SingPassLoginException);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    public function test_jwt_payload_exception_renders_redirect(): void
    {
        Route::get('/login')->name('login');

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: [],
            userInfoData: null,
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    public function test_null_id_token_payload_does_not_fire_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: null,
            userInfoData: ['person_info' => []],
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        Event::assertNotDispatched(SingPassSuccessfulLoginEvent::class);
    }

    public function test_session_cleanup_happens_on_unhandled_exception(): void
    {
        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'test-client-id',
            redirectUri: 'https://example.com/callback',
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')
            ->once()
            ->andThrow(new \RuntimeException('Unexpected error'));
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $this->expectException(\RuntimeException::class);
        $controller->__invoke($request, $fapiCallbackMock);
    }
}
