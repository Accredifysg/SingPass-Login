<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Http\Controllers\GetSingPassCallbackController;
use Accredifysg\SingPassLogin\SingPassLogin;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use PHPUnit\Framework\MockObject\MockObject;

class GetSingPassCallbackControllerTest extends TestCase
{
    public function testInvokeCallsHandleCallbackAndRedirects(): void
    {
        // Create a mock of SingPassLogin using PHPUnit's mocking
        /** @var SingPassLogin|MockObject $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->once())
            ->method('handleCallback')
            ->with('test-code', 'test-state');

        // Mock the redirect response
        $redirectMock = $this->createMock(RedirectResponse::class);
        Redirect::shouldReceive('intended')->once()->andReturn($redirectMock);

        // Create an instance of the controller
        $controller = new GetSingPassCallbackController;

        // Create the request
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        // Call the __invoke method
        $response = $controller->__invoke($request, $singPassLoginMock);

        // Assert that the response is a RedirectResponse
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testSingPassLoginExceptionRender(): void
    {
        // Mock the SingPassLogin class
        $singPassLoginMock = $this->createMock(SingPassLogin::class);

        $singPassLoginMock->expects($this->once())
            ->method('handleCallback')
            ->with('test-code', 'test-state')
            ->willThrowException(new SingPassLoginException);

        // Create an instance of the controller
        $controller = new GetSingPassCallbackController;

        // Create the request
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        // Call the method and capture the response
        $response = $controller->__invoke($request, $singPassLoginMock);

        // Assert that the response is a redirect
        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Assert that it redirects back
        $this->assertEquals(url()->previous(), $response->getTargetUrl());

        // Assert that the session contains the expected error message
        $this->assertEquals([
            'singpass' => [
                [
                    'title' => 'SingPass Login Error',
                    'description' => 'User not found.',
                ],
            ],
        ], session('errors')->getBag('default')->messages());
    }

    protected function getPackageProviders($app): array
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
