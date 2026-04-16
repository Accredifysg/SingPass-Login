<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\TokenExchangeException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TokenExchangeExceptionTest extends TestCase
{
    public function test_exception_inheritance(): void
    {
        $exception = new TokenExchangeException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function test_default_values(): void
    {
        $exception = new TokenExchangeException;
        $this->assertEquals(500, $exception->getStatusCode());
        $this->assertEquals('Token exchange request failed', $exception->getMessage());
    }

    public function test_custom_values(): void
    {
        $exception = new TokenExchangeException(400, 'Custom message');
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function test_render(): void
    {
        $exception = new TokenExchangeException(422, 'Custom error message');
        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '{}', true);
        $this->assertEquals(['message' => 'Custom error message'], $content);
    }
}
