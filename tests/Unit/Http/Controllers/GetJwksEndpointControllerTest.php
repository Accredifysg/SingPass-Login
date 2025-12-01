<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Jose\Component\KeyManagement\JWKFactory;

class GetJwksEndpointControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $mockJwksContent;

    protected function setUp(): void
    {
        parent::setUp();

        // Create new key
        $newKey = JWKFactory::createECKey('P-256', ['kid' => 'test-kid'])->all();

        // Create a mock JWKS file
        $this->mockJwksContent = [
            'keys' => [
                $newKey,
            ],
        ];

        Config::set('singpass-login.jwks', json_encode($this->mockJwksContent));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_invoke_returns_jwks(): void
    {
        $controller = new GetJwksEndpointController;
        $response = $controller->__invoke(request());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($this->mockJwksContent, $response->getData(true));
    }

    public function test_invoke_throws_exception_when_jwks_file_is_invalid(): void
    {
        // Replace the JWKS env var with invalid JSON
        Config::set('singpass-login.jwks', 'invalid json');

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS is an invalid JSON string.');

        $controller = new GetJwksEndpointController;
        $controller->__invoke(request());
    }

    public function test_invoke_throws_exception_when_jwks_file_is_missing(): void
    {
        // Delete the JWKS env var
        Config::set('singpass-login.jwks');

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS environment variable not set.');

        $controller = new GetJwksEndpointController;
        $controller->__invoke(request());
    }

    protected function getPackageProviders($app): array
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
