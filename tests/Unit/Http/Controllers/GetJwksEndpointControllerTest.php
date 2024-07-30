<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Jose\Component\KeyManagement\JWKFactory;
use JsonException;

class GetJwksEndpointControllerTest extends TestCase
{
    public function setUp(): void
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

        Config::set('services.singpass-login.jwks', json_encode($this->mockJwksContent));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function testInvokeReturnsJwks()
    {
        $controller = new GetJwksEndpointController;
        $response = $controller->__invoke(request());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($this->mockJwksContent, $response->getData(true));
    }

    /**
     * @throws JsonException
     */
    public function testInvokeThrowsExceptionWhenJwksFileIsInvalid()
    {
        // Replace the JWKS env var with invalid JSON
        Config::set('services.singpass-login.jwks', 'invalid json');

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS is an invalid JSON string.');

        $controller = new GetJwksEndpointController;
        $controller->__invoke(request());
    }

    /**
     * @throws JsonException
     */
    public function testInvokeThrowsExceptionWhenJwksFileIsMissing()
    {
        // Delete the JWKS env var
        Config::set('services.singpass-login.jwks');

        $this->expectException(JwksInvalidException::class);
        $this->expectExceptionMessage('JWKS environment variable not set.');

        $controller = new GetJwksEndpointController;
        $controller->__invoke(request());
    }

    protected function getPackageProviders($app)
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
