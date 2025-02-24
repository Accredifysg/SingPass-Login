<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Exceptions;

use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Exceptions\SingPassTokenException;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SingPassLoginExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new SingPassLoginException;
        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function testDefaultValues(): void
    {
        $exception = new SingPassLoginException;
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('User not found.', $exception->getMessage());
    }

    public function testCustomValues(): void
    {
        $exception = new SingPassTokenException(400, 'Custom message');
        $this->assertEquals(400, $exception->getStatusCode());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function testRender(): void
    {
        $exception = new SingPassLoginException;

        $response = $exception->render();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(url()->previous(), $response->getTargetUrl());
        $this->assertEquals([
            'singpass' => [
                [
                    'title' => 'SingPass Login Error',
                    'description' => 'User not found.',
                ],
            ],
        ], session('errors')->getBag('default')->messages());
    }
}
