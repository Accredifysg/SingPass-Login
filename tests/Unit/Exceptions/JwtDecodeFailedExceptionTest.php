<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\JwtDecodeFailedException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JwtDecodeFailedExceptionTest extends TestCase
{
    public function testExceptionInheritance()
    {
        $exception = new JwtDecodeFailedException();
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function testDefaultValues()
    {
        $exception = new JwtDecodeFailedException();
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('JWT Decoding Failed', $exception->getMessage());
    }

    public function testCustomValues()
    {
        $exception = new JwtDecodeFailedException(400, 'Custom message');
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function testRender()
    {
        $exception = new JwtDecodeFailedException(422, 'Custom error message');
        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['message' => 'Custom error message'], $content);
    }
}
