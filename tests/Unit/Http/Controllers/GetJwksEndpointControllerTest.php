<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Http\Controllers\GetJwksEndpointController;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Http\JsonResponse;

class GetJwksEndpointControllerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Create a mock JWKS file
        $this->mockJwksContent = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => '1',
                    'use' => 'sig',
                    'n' => 'someModulus',
                    'e' => 'AQAB'
                ]
            ]
        ];

        File::put(storage_path('jwks/jwks.json'), json_encode($this->mockJwksContent));
    }

    protected function tearDown(): void
    {
        // Clean up the mock file
        if (File::exists(storage_path('jwks/jwks.json'))) {
            File::delete(storage_path('jwks/jwks.json'));
        }

        parent::tearDown();
    }

    public function testInvokeReturnsJwks()
    {
        $controller = new GetJwksEndpointController();
        $response = $controller->__invoke(request());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($this->mockJwksContent, $response->getData(true));
    }

    public function testInvokeThrowsExceptionWhenJwksFileIsInvalid()
    {
        // Replace the JWKS file with invalid JSON
        File::put(storage_path('jwks/jwks.json'), 'invalid json');

        $this->expectException(JwksInvalidException::class);

        $controller = new GetJwksEndpointController();
        $controller->__invoke(request());
    }

    public function testInvokeThrowsExceptionWhenJwksFileIsMissing()
    {
        // Delete the JWKS file
        File::delete(storage_path('jwks/jwks.json'));

        $this->expectException(JwksInvalidException::class);

        $controller = new GetJwksEndpointController();
        $controller->__invoke(request());
    }

    protected function getPackageProviders($app)
    {
        return [
            SingPassLoginServiceProvider::class
        ];
    }
}