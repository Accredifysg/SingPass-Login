<?php

namespace Accredifysg\SingPassLogin\Tests\Feature;

use Accredifysg\SingPassLogin\DTOs\TokenResponseDto;
use Accredifysg\SingPassLogin\Events\MyInfoDataRetrievedEvent;
use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Accredifysg\SingPassLogin\SingPassLogin;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Jose\Component\Core\JWKSet;

class SingPassLoginTest extends TestCase
{
    public function test_handle_callback(): void
    {
        // Create mock services
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);
        $getUserInfoService = $this->createMock(GetUserInfoServiceInterface::class);

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

        $tokenResponseDto = new TokenResponseDto('jwe_token');

        // Set expectations on mock services
        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery');
        $getSingPassTokenService->expects($this->once())->method('getToken')
            ->with('test_code', 'test-code-verifier', 'test-state')
            ->willReturn($tokenResponseDto);
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
            $getSingPassJwksService,
            $getUserInfoService
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

    public function test_handle_callback_with_myinfo_data(): void
    {
        // Create mock services
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);
        $getUserInfoService = $this->createMock(GetUserInfoServiceInterface::class);

        $tokenResponseDto = new TokenResponseDto('jwe_token', 'access_token_value');
        $myInfoData = [
            'sub' => 's=S8829314B,u=1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9',
            'name' => ['value' => 'John Doe'],
            'email' => ['value' => 'john@example.com'],
        ];

        // Set expectations on mock services
        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery');
        $getSingPassTokenService->expects($this->once())->method('getToken')
            ->with('test_code', 'test-code-verifier', 'test-state')
            ->willReturn($tokenResponseDto);
        // Note: JWT decryption/decoding is NOT called for MyInfo flow (it's in the else block)
        $singPassJwtService->expects($this->never())->method('jweDecrypt');
        $getSingPassJwksService->expects($this->never())->method('getSingPassJwks');
        $singPassJwtService->expects($this->never())->method('jwtDecode');
        $singPassJwtService->expects($this->never())->method('verifyPayload');
        $getUserInfoService->expects($this->once())->method('shouldCallUserInfo')
            ->with('access_token_value')
            ->willReturn(true);
        $getUserInfoService->expects($this->once())->method('getUserInfo')
            ->with('access_token_value')
            ->willReturn($myInfoData);

        // Spy on the event
        Event::fake();

        // Create an instance of SingPassLogin
        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService,
            $getUserInfoService
        );

        // Call the method
        $singPassLogin->handleCallback('test_code', 'test-state', 'test-code-verifier');

        // Assert that MyInfoDataRetrievedEvent was dispatched
        Event::assertDispatched(MyInfoDataRetrievedEvent::class, function ($event) use ($myInfoData) {
            return $event->getMyInfoData() === $myInfoData
                && $event->getState() === 'test-state';
        });

        // Assert that SingPassSuccessfulLoginEvent was NOT dispatched
        Event::assertNotDispatched(SingPassSuccessfulLoginEvent::class);
    }

    public function test_handle_callback_with_exception(): void
    {
        // Create mock services
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);
        $getUserInfoService = $this->createMock(GetUserInfoServiceInterface::class);

        // Set expectation to throw an exception
        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery')
            ->willThrowException(new OpenIdDiscoveryException);

        // Create an instance of SingPassLogin
        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService,
            $getUserInfoService
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
