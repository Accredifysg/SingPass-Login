<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\OpenIdDiscoveryException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OpenIdDiscoveryExceptionTest extends TestCase
{
    public function test_exception_inheritance()
    {
        $exception = new OpenIdDiscoveryException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function test_default_values()
    {
        $exception = new OpenIdDiscoveryException;
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('Open ID Discovery call failed', $exception->getMessage());
    }

    public function test_custom_values()
    {
        $exception = new OpenIdDiscoveryException(400, 'Custom message');
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function test_render()
    {
        $exception = new OpenIdDiscoveryException(422, 'Custom error message');
        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['message' => 'Custom error message'], $content);
    }
}
