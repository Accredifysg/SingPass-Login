<?php

namespace Accredifysg\SingPassLogin\Tests\Unit;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassJwksServiceInterface;
use Accredifysg\SingPassLogin\Interfaces\GetSingPassTokenServiceInterface;
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

    public function setUp(): void
    {
        parent::setUp();

        // Create mock services
        $openIdDiscoveryService = Mockery::mock(OpenIdDiscoveryServiceInterface::class);
        $getSingPassTokenService = Mockery::mock(GetSingPassTokenServiceInterface::class);
        $singPassJwtService = Mockery::mock(SingPassJwtServiceInterface::class);
        $getSingPassJwksService = Mockery::mock(GetSingPassJwksServiceInterface::class);

        // Initialize your class here if needed
        $this->singPassLogin = new SingPassLogin('123', '456', $openIdDiscoveryService, $getSingPassTokenService, $singPassJwtService, $getSingPassJwksService);
    }

    private function callPrivateMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new ReflectionMethod($object, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $parameters);
    }

    public function test_get_sing_pass_user_success()
    {
        // Create a mock payload
        $payload = [
            'sub' => 's=S8829314B,u=1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9',
        ];

        // Call the private method using reflection
        $singPassUser = $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);

        // Assert the method returns a SingPassUser object
        $this->assertInstanceOf(SingPassUser::class, $singPassUser);

        // Assert the SingPassUser object contains the expected values
        $this->assertEquals('S8829314B', $singPassUser->getNric());
        $this->assertEquals('1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9', $singPassUser->getUuid());
    }

    public function test_get_sing_pass_user_invalid_payload()
    {
        // Create a mock payload with missing NRIC and UUID
        $payload = [
            'sub' => 'S1234567A,',
        ];

        // Expect the JwtPayloadException to be thrown
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('NRIC or UUID is empty');

        // Call the private method using reflection
        $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);
    }

    public function test_get_sing_pass_user_empty_sub()
    {
        // Create a mock payload with empty sub
        $payload = [
            'sub' => '',
        ];

        // Expect the JwtPayloadException to be thrown
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Sub is empty');

        // Call the private method using reflection
        $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);
    }

    public function test_get_sing_pass_user_invalid_sub_format()
    {
        // Create a mock payload with invalid sub format
        $payload = [
            'sub' => 'invalidformat',
        ];

        // Expect the JwtPayloadException to be thrown
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Cannot get IC and UUID');

        // Call the private method using reflection
        $this->callPrivateMethod($this->singPassLogin, 'getSingPassUser', [$payload]);
    }
}
