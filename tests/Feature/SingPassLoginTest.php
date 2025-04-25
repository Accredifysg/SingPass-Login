<?php

namespace Accredifysg\SingPassLogin\Tests\Feature;

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

class SingPassLoginTest extends TestCase
{
    public function testHandleCallback(): void
    {
        // Create mock services
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);

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
        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery');
        $getSingPassTokenService->expects($this->once())->method('getToken')
            ->with('test_code', 'test-code-verifier')
            ->willReturn('jwe_token');
        $singPassJwtService->expects($this->once())->method('jweDecrypt')
            ->with('jwe_token')
            ->willReturn('jwt_token');
        $getSingPassJwksService->expects($this->once())->method('getSingPassJwks')
            ->willReturn($jwks);
        $singPassJwtService->expects($this->once())->method('jwtDecode')
            ->with('jwt_token', $jwks)
            ->willReturn(['sub' => 's=S8829314B,u=1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9']);
        $singPassJwtService->expects($this->once())->method('verifyPayload')
            ->with(['sub' => 's=S8829314B,u=1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9']);

        // Spy on the event
        Event::fake();

        // Create an instance of SingPassLogin
        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService
        );

        // Call the method
        $singPassLogin->handleCallback('test_code', 'test-state', 'test-code-verifier');

        // Assert that the event was dispatched
        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getNric() === 'S8829314B';
        });

        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getUuid() === '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9';
        });
    }

    public function test_handle_callback_with_exception(): void
    {
        // Create mock services
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);

        // Set expectation to throw an exception
        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery')
            ->willThrowException(new OpenIdDiscoveryException);

        // Create an instance of SingPassLogin
        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService
        );

        // Expect an exception
        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('Open ID Discovery call failed');

        // Call the method
        $singPassLogin->handleCallback('test-code', 'test-state', 'test-code-verifier');
    }

    protected function getPackageProviders($app): array
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
