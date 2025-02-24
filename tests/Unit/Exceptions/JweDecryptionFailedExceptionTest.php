<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\JweDecryptionFailedException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JweDecryptionFailedExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new JweDecryptionFailedException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function testDefaultValues(): void
    {
        $exception = new JweDecryptionFailedException;
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('JWE Decryption Failed.', $exception->getMessage());
    }

    public function testCustomValues(): void
    {
        $exception = new JweDecryptionFailedException(400, 'Custom message');
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function testRender(): void
    {
        $exception = new JweDecryptionFailedException(422, 'Custom error message');
        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(['message' => 'Custom error message'], $content);
    }
}
