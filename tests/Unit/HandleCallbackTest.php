<?php

namespace Accredifysg\SingPassLogin\Tests\Unit;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Accredifysg\SingPassLogin\SingPassLogin;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Jose\Component\Core\JWKSet;
use Mockery;

class HandleCallbackTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testHandleCallback()
    {
        // Create mock services
        $openIdDiscoveryService = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = Mockery::mock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = Mockery::mock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = Mockery::mock(GetSingPassJwksServiceInterface::class);

        $jwks = JWKSet::createFromKeyData([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => '1b94c',
                    'use' => 'sig',
                    'n' => '...',
                    'e' => 'AQAB',
                ],
            ],
        ]);

        // Set expectations on mock services
        $openIdDiscoveryService->shouldReceive('cacheOpenIdDiscovery')->once();
        $getSingPassTokenService->shouldReceive('getToken')->once()->with('test_code')->andReturn('jwe_token');
        $singPassJwtService->shouldReceive('jweDecrypt')->once()->with('jwe_token')->andReturn('jwt_token');
        $getSingPassJwksService->shouldReceive('getSingPassJwks')->once()->andReturn($jwks);
        $singPassJwtService->shouldReceive('jwtDecode')->once()->with('jwt_token', $jwks)->andReturn(['sub' => 's=S8829314B,u=1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9']);
        $singPassJwtService->shouldReceive('verifyPayload')->once()->with(['sub' => 's=S8829314B,u=1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9']);

        // Spy on the event
        Event::fake();

        // Create an instance of SingPassLogin
        $singPassLogin = new SingPassLogin(
            'test_code',
            'test_state',
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService
        );

        // Call the method
        $singPassLogin->handleCallback();

        // Assert that the event was dispatched
        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getNric() === 'S8829314B';
        });

        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getUuid() === '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9';
        });
    }

    public function testHandleCallbackWithException()
    {
        // Create mock services
        $openIdDiscoveryService = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = Mockery::mock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = Mockery::mock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = Mockery::mock(GetSingPassJwksServiceInterface::class);

        // Set expectation to throw an exception
        $openIdDiscoveryService->shouldReceive('cacheOpenIdDiscovery')->once()->andThrow(new OpenIdDiscoveryException());

        // Create an instance of SingPassLogin
        $singPassLogin = new SingPassLogin(
            'test_code',
            'test_state',
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService
        );

        // Expect an exception
        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('Open ID Discovery call failed');

        // Call the method
        $singPassLogin->handleCallback();
    }

    protected function getPackageProviders($app)
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
