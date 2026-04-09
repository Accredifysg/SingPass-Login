<?php

namespace Accredifysg\SingPassLogin\Tests\Unit;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetUserInfoServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\OpenIdDiscoveryServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\SingPassJwtServiceInterface;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\SingPassLogin;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Mockery;
use ReflectionMethod;

class GetSingPassUserTest extends TestCase
{
    protected SingPassLogin $singPassLogin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var OpenIdDiscoveryServiceInterface $openIdDiscoveryService */
        $openIdDiscoveryService = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        /** @var GetSingPassTokenServiceInterface $getSingPassTokenService */
        $getSingPassTokenService = Mockery::mock(GetSingPassTokenServiceInterface::class);
        /** @var SingPassJwtServiceInterface $singPassJwtService */
        $singPassJwtService = Mockery::mock(SingPassJwtServiceInterface::class);
        /** @var GetSingPassJwksServiceInterface $getSingPassJwksService */
        $getSingPassJwksService = Mockery::mock(GetSingPassJwksServiceInterface::class);
        /** @var GetUserInfoServiceInterface $getUserInfoService */
        $getUserInfoService = Mockery::mock(GetUserInfoServiceInterface::class);

        $this->singPassLogin = new SingPassLogin($openIdDiscoveryService, $getSingPassTokenService, $singPassJwtService, $getSingPassJwksService, $getUserInfoService);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    private function callPrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionMethod($object, $methodName);

        return $reflection->invokeArgs($object, $parameters);
    }

    public function test_get_sing_pass_user_with_sub_attributes(): void
    {
        $payload = [
            'sub' => '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9',
            'sub_attributes' => [
                'identity_number' => 'S8829314B',
                'account_type' => 'standard',
                'identity_coi' => 'SG',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'mobileno' => '91234567',
            ],
        ];

        $singPassUser = $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);

        $this->assertInstanceOf(SingPassUser::class, $singPassUser);
        $this->assertEquals('1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9', $singPassUser->getUuid());
        $this->assertEquals('S8829314B', $singPassUser->getNric());
        $this->assertEquals('standard', $singPassUser->getAccountType());
        $this->assertEquals('SG', $singPassUser->getIdentityCoi());
        $this->assertEquals('John Doe', $singPassUser->getName());
        $this->assertEquals('john@example.com', $singPassUser->getEmail());
        $this->assertEquals('91234567', $singPassUser->getMobileNo());
    }

    public function test_get_sing_pass_user_uuid_only(): void
    {
        $payload = [
            'sub' => '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9',
        ];

        $singPassUser = $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);

        $this->assertInstanceOf(SingPassUser::class, $singPassUser);
        $this->assertEquals('1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9', $singPassUser->getUuid());
        $this->assertNull($singPassUser->getNric());
        $this->assertNull($singPassUser->getAccountType());
        $this->assertNull($singPassUser->getIdentityCoi());
        $this->assertNull($singPassUser->getName());
        $this->assertNull($singPassUser->getEmail());
        $this->assertNull($singPassUser->getMobileNo());
    }

    public function test_get_sing_pass_user_foreign_account(): void
    {
        $payload = [
            'sub' => '7c9c72ec-5be2-495a-a78e-61e809a2a236',
            'sub_attributes' => [
                'identity_number' => 'K28394589',
                'account_type' => 'foreign',
                'identity_coi' => 'TK',
                'name' => 'Larry Doe',
                'email' => 'larrydoe@gmail.com',
            ],
        ];

        $singPassUser = $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);

        $this->assertInstanceOf(SingPassUser::class, $singPassUser);
        $this->assertEquals('7c9c72ec-5be2-495a-a78e-61e809a2a236', $singPassUser->getUuid());
        $this->assertEquals('K28394589', $singPassUser->getNric());
        $this->assertEquals('foreign', $singPassUser->getAccountType());
        $this->assertEquals('TK', $singPassUser->getIdentityCoi());
        $this->assertEquals('Larry Doe', $singPassUser->getName());
        $this->assertEquals('larrydoe@gmail.com', $singPassUser->getEmail());
        $this->assertNull($singPassUser->getMobileNo());
    }

    public function test_get_sing_pass_user_empty_sub(): void
    {
        $payload = [
            'sub' => '',
        ];

        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Sub is empty');

        $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);
    }

    public function test_get_sing_pass_user_missing_sub(): void
    {
        $payload = [];

        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Sub is empty');

        $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);
    }
}
