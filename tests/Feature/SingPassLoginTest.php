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
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;

class SingPassLoginTest extends TestCase
{
    private JWK $dpopKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dpopKey = JWKFactory::createECKey('P-256');
    }

    public function test_handle_callback(): void
    {
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

        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery');
        $getSingPassTokenService->expects($this->once())->method('getToken')
            ->with('test_code', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback')
            ->willReturn($tokenResponseDto);
        $singPassJwtService->expects($this->once())->method('jweDecrypt')
            ->with('jwe_token')
            ->willReturn('jwt_token');
        $getSingPassJwksService->expects($this->once())->method('getSingPassJwks')
            ->willReturn($jwks);
        $singPassJwtService->expects($this->once())->method('jwtDecode')
            ->with('jwt_token', $jwks)
            ->willReturn([
                'sub' => '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9',
                'sub_attributes' => [
                    'identity_number' => 'S8829314B',
                    'account_type' => 'standard',
                    'identity_coi' => 'SG',
                ],
            ]);
        $singPassJwtService->expects($this->once())->method('verifyPayload');

        Event::fake();

        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService,
            $getUserInfoService
        );

        $singPassLogin->handleCallback('test_code', 'test-state', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback');

        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getNric() === 'S8829314B';
        });

        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getUuid() === '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9';
        });

        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getAccountType() === 'standard';
        });
    }

    public function test_handle_callback_without_sub_attributes(): void
    {
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);
        $getUserInfoService = $this->createMock(GetUserInfoServiceInterface::class);

        $jwks = JWKSet::createFromKeyData([
            'keys' => [['kty' => 'RSA', 'kid' => '1b94c', 'use' => 'sig', 'n' => '...', 'e' => 'AQAB']],
        ]);

        $tokenResponseDto = new TokenResponseDto('jwe_token');

        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery');
        $getSingPassTokenService->expects($this->once())->method('getToken')
            ->willReturn($tokenResponseDto);
        $singPassJwtService->expects($this->once())->method('jweDecrypt')
            ->willReturn('jwt_token');
        $getSingPassJwksService->expects($this->once())->method('getSingPassJwks')
            ->willReturn($jwks);
        $singPassJwtService->expects($this->once())->method('jwtDecode')
            ->willReturn(['sub' => '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9']);
        $singPassJwtService->expects($this->once())->method('verifyPayload');

        Event::fake();

        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService,
            $getUserInfoService
        );

        $singPassLogin->handleCallback('test_code', 'test-state', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback');

        Event::assertDispatched(SingPassSuccessfulLoginEvent::class, function ($event) {
            return $event->getSingPassUser()->getUuid() === '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9'
                && $event->getSingPassUser()->getNric() === null;
        });
    }

    public function test_handle_callback_with_myinfo_data(): void
    {
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);
        $getUserInfoService = $this->createMock(GetUserInfoServiceInterface::class);

        $tokenResponseDto = new TokenResponseDto('jwe_token', 'access_token_value');
        $myInfoData = [
            'uinfin' => ['value' => 'S8829314B'],
            'name' => ['value' => 'John Doe'],
            'email' => ['value' => 'john@example.com'],
        ];

        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery');
        $getSingPassTokenService->expects($this->once())->method('getToken')
            ->with('test_code', 'test-code-verifier', $this->dpopKey, 'myinfo-client-id', 'https://example.com/myinfo-callback')
            ->willReturn($tokenResponseDto);
        $singPassJwtService->expects($this->never())->method('jweDecrypt');
        $getSingPassJwksService->expects($this->never())->method('getSingPassJwks');
        $singPassJwtService->expects($this->never())->method('jwtDecode');
        $singPassJwtService->expects($this->never())->method('verifyPayload');
        $getUserInfoService->expects($this->once())->method('shouldCallUserInfo')
            ->with('access_token_value')
            ->willReturn(true);
        $getUserInfoService->expects($this->once())->method('getUserInfo')
            ->with('access_token_value', $this->dpopKey)
            ->willReturn($myInfoData);

        Event::fake();

        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService,
            $getUserInfoService
        );

        $singPassLogin->handleCallback('test_code', 'test-state', 'test-code-verifier', $this->dpopKey, 'myinfo-client-id', 'https://example.com/myinfo-callback');

        Event::assertDispatched(MyInfoDataRetrievedEvent::class, function ($event) use ($myInfoData) {
            return $event->getMyInfoData() === $myInfoData
                && $event->getState() === 'test-state';
        });

        Event::assertNotDispatched(SingPassSuccessfulLoginEvent::class);
    }

    public function test_handle_callback_with_exception(): void
    {
        $openIdDiscoveryService = $this->createMock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = $this->createMock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = $this->createMock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = $this->createMock(GetSingPassJwksServiceInterface::class);
        $getUserInfoService = $this->createMock(GetUserInfoServiceInterface::class);

        $openIdDiscoveryService->expects($this->once())->method('cacheOpenIdDiscovery')
            ->willThrowException(new OpenIdDiscoveryException);

        $singPassLogin = new SingPassLogin(
            $openIdDiscoveryService,
            $getSingPassTokenService,
            $singPassJwtService,
            $getSingPassJwksService,
            $getUserInfoService
        );

        $this->expectException(OpenIdDiscoveryException::class);
        $this->expectExceptionMessage('Open ID Discovery call failed');

        $singPassLogin->handleCallback('test-code', 'test-state', 'test-code-verifier', $this->dpopKey, 'test-client-id', 'https://example.com/callback');
    }

    protected function getPackageProviders($app): array
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
