<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SingPassTokenExceptionTest extends TestCase
{
    public function testExceptionInheritance()
    {
        $exception = new SingPassTokenException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function testDefaultValues()
    {
        $exception = new SingPassTokenException;
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('GET request to SingPass Token endpoint failed', $exception->getMessage());
    }

    public function testCustomValues()
    {
        $exception = new SingPassTokenException(400, 'Custom message');
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function testRender()
    {
        $exception = new SingPassTokenException(422, 'Custom error message');
        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['message' => 'Custom error message'], $content);
    }
}
