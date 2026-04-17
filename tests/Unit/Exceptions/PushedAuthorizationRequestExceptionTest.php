<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\PushedAuthorizationRequestException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PushedAuthorizationRequestExceptionTest extends TestCase
{
    public function test_exception_inheritance(): void
    {
        $exception = new PushedAuthorizationRequestException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function test_default_constructor_values(): void
    {
        $exception = new PushedAuthorizationRequestException;

        $this->assertSame(500, $exception->getStatusCode());
        $this->assertSame('Pushed Authorization Request failed', $exception->getMessage());
        $this->assertSame('server_error', $exception->getErrorCode());
        $this->assertNull($exception->getErrorDescription());
    }

    public function test_custom_constructor_values(): void
    {
        $exception = new PushedAuthorizationRequestException(
            statusCode: 502,
            message: 'PAR gateway timeout',
            errorCode: 'temporarily_unavailable',
            errorDescription: 'The PAR endpoint did not respond in time',
        );

        $this->assertSame(502, $exception->getStatusCode());
        $this->assertSame('PAR gateway timeout', $exception->getMessage());
        $this->assertSame('temporarily_unavailable', $exception->getErrorCode());
        $this->assertSame('The PAR endpoint did not respond in time', $exception->getErrorDescription());
    }

    public function test_render_returns_json_with_description_when_set(): void
    {
        $exception = new PushedAuthorizationRequestException(
            statusCode: 400,
            message: 'PAR rejected',
            errorCode: 'invalid_request',
            errorDescription: 'redirect_uri is not registered',
        );

        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($content);
        $this->assertSame([
            'error' => 'invalid_request',
            'error_description' => 'redirect_uri is not registered',
        ], $content);
    }

    public function test_render_uses_message_when_error_description_is_null(): void
    {
        $exception = new PushedAuthorizationRequestException(
            statusCode: 503,
            message: 'Service unavailable',
            errorCode: 'server_error',
            errorDescription: null,
        );

        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(503, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($content);
        $this->assertSame([
            'error' => 'server_error',
            'error_description' => 'Service unavailable',
        ], $content);
    }

    public function test_render_default_body_uses_default_message_for_description(): void
    {
        $exception = new PushedAuthorizationRequestException;

        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());

        $content = json_decode($response->getContent() ?: '{}', true);
        $this->assertIsArray($content);
        $this->assertSame([
            'error' => 'server_error',
            'error_description' => 'Pushed Authorization Request failed',
        ], $content);
    }
}
