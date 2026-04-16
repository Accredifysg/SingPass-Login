<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers\SingPass;

use Accredifysg\SingPassLogin\DTOs\FapiCallbackResult;
use Accredifysg\SingPassLogin\DTOs\FapiSessionContext;
use Accredifysg\SingPassLogin\Events\MyInfoDataRetrievedEvent;
use Accredifysg\SingPassLogin\Http\Controllers\SingPass\MyInfoCallbackController;
use Accredifysg\SingPassLogin\Services\FapiCallbackService;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Jose\Component\KeyManagement\JWKFactory;
use Mockery;

class MyInfoCallbackControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SingPassLoginServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('myinfo.client_id', 'myinfo-client-id');
        Config::set('myinfo.redirect_uri', 'https://example.com/myinfo-callback');
        Config::set('myinfo.discovery_endpoint', 'https://example.com/discovery');
        Config::set('myinfo.domain', 'https://example.com');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_successful_myinfo_fires_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');
        $myInfoData = ['uinfin' => ['value' => 'S1234567A']];

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'myinfo-client-id',
            redirectUri: 'https://example.com/myinfo-callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: null,
            userInfoData: $myInfoData,
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once()->with('test-state');

        $controller = new MyInfoCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        Event::assertDispatched(MyInfoDataRetrievedEvent::class, function (MyInfoDataRetrievedEvent $event) use ($myInfoData): bool {
            return $event->getMyInfoData() === $myInfoData
                && $event->getState() === 'test-state';
        });
    }

    public function test_null_userinfo_does_not_fire_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'myinfo-client-id',
            redirectUri: 'https://example.com/myinfo-callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: null,
            userInfoData: null,
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once();

        $controller = new MyInfoCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $controller->__invoke($request, $fapiCallbackMock);

        Event::assertNotDispatched(MyInfoDataRetrievedEvent::class);
    }

    public function test_empty_userinfo_array_still_fires_event(): void
    {
        Event::fake();

        $dpopKey = JWKFactory::createECKey('P-256');

        $session = new FapiSessionContext(
            code: 'test-code',
            state: 'test-state',
            codeVerifier: 'test-verifier',
            dpopKey: $dpopKey,
            clientId: 'myinfo-client-id',
            redirectUri: 'https://example.com/myinfo-callback',
        );

        $result = new FapiCallbackResult(
            idTokenPayload: null,
            userInfoData: [],
        );

        $fapiCallbackMock = Mockery::mock(FapiCallbackService::class);
        $fapiCallbackMock->shouldReceive('validateAndRetrieveSession')->once()->andReturn($session);
        $fapiCallbackMock->shouldReceive('processCallback')->once()->andReturn($result);
        $fapiCallbackMock->shouldReceive('cleanupSession')->once();

        $controller = new MyInfoCallbackController;
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        $response = $controller->__invoke($request, $fapiCallbackMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        Event::assertDispatched(MyInfoDataRetrievedEvent::class);
    }
}
