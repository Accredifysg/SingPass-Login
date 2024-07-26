<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JwksInvalidExceptionTest extends TestCase
{
    public function testExceptionInheritance()
    {
        $exception = new JwksInvalidException();
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function testDefaultValues()
    {
        $exception = new JwksInvalidException();
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('JWKS Invalid', $exception->getMessage());
    }

    public function testCustomValues()
    {
        $exception = new JwksInvalidException(400, 'Custom message');
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function testRender()
    {
        $exception = new JwksInvalidException(422, 'Custom error message');
        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['message' => 'Custom error message'], $content);
    }
}
