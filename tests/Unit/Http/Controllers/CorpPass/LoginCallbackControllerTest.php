<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\CorpPass;

use Accredifysg\SingPassLogin\DTOs\FapiCallbackResult;
use Accredifysg\SingPassLogin\DTOs\FapiSessionContext;
use Accredifysg\SingPassLogin\Events\CorpPassDataRetrievedEvent;
use Accredifysg\SingPassLogin\Events\CorpPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\AuthFlowException;
use Accredifysg\SingPassLogin\Exceptions\CorpPassLoginException;
use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Http\Controllers\CorpPass\LoginCallbackController;
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
        Config::set('corppass-login.client_id', 'cp-client-id');
        Config::set('corppass-login.redirect_uri', 'https://example.com/cp-callback');
        Config::set('corppass-login.discovery_endpoint', 'https://corppass.example.com/discovery');
        Config::set('corppass-login.domain', 'https://corppass.example.com');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_id_token_path_fires_login_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'cp-client-id',
            redirectUri: 'https://example.com/cp-callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: [
                'sub' => '200000001A',
                'sub_attributes' => ['entity_name' => 'Test Corp'],
                'act' => [
                    'sub' => 'actor-uuid',
                    'sub_attributes' => ['identity_number' => 'S1234567A'],
                ],
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
        Event::assertDispatched(CorpPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getCorpPassUser()->getEntityId() === '200000001A'
                && $event->getCorpPassUser()->getIdentityNumber() === 'S1234567A'
                && $event->getState() === 'test-state';
        });
        Event::assertNotDispatched(CorpPassDataRetrievedEvent::class);
    }

    public function test_userinfo_path_fires_data_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'cp-client-id',
            redirectUri: 'https://example.com/cp-callback',
        );

        $authData = ['auth_info' => ['role' => 'admin']];

        $result = new FapiCallbackResult(
            idTokenPayload: [
                'sub' => '200000001A',
                'act' => ['sub' => 'actor-uuid'],
            ],
            userInfoData: $authData,
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        Event::assertDispatched(CorpPassDataRetrievedEvent::class, function ($event) use ($authData) {
            return $event->getCorpPassData() === $authData
                && $event->getState() === 'test-state';
        });
        Event::assertDispatched(CorpPassSuccessfulLoginEvent::class);
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

    public function test_corppass_login_exception_renders_redirect(): void
    {
        Route::get('/login')->name('login');

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'cp-client-id',
            redirectUri: 'https://example.com/cp-callback',
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')
            ->once()
            ->andThrow(new CorpPassLoginException);
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
            clientId: 'cp-client-id',
            redirectUri: 'https://example.com/cp-callback',
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')
            ->once()
            ->andThrow(new JwtPayloadException(400, 'Sub (entity ID) is empty'));
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('login'), $response->getTargetUrl());
    }

    public function test_session_cleanup_happens_on_unhandled_exception(): void
    {
        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'cp-client-id',
            redirectUri: 'https://example.com/cp-callback',
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

    public function test_null_userinfo_does_not_fire_data_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'cp-client-id',
            redirectUri: 'https://example.com/cp-callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: [
                'sub' => '200000001A',
                'act' => ['sub' => 'actor-uuid'],
            ],
            userInfoData: null,
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once();

        $controller = new LoginCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $controller->__invoke($request, $fapiCallbackMock);

        Event::assertNotDispatched(CorpPassDataRetrievedEvent::class);
        Event::assertDispatched(CorpPassSuccessfulLoginEvent::class);
    }
}
