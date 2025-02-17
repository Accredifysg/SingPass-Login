<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Http\Controllers;

use Accredifysg\SingPassLogin\Http\Controllers\PostSingPassCallbackController;
use Accredifysg\SingPassLogin\SingPassLogin;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use PHPUnit\Framework\MockObject\MockObject;

class PostSingPassCallbackControllerTest extends TestCase
{
    public function testInvokeCallsHandleCallbackAndRedirects()
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
        $controller = new PostSingPassCallbackController;

        // Create the request
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        // Call the __invoke method
        $response = $controller->__invoke($request, $singPassLoginMock);

        // Assert that the response is a RedirectResponse
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testInvokeHandlesExceptions()
    {
        // Create a mock of SingPassLogin using PHPUnit's mocking
        /** @var SingPassLogin|MockObject $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->once())
            ->method('handleCallback')
            ->with('test-code', 'test-state')
            ->willThrowException(new \Exception('Test exception'));

        // Create an instance of the controller
        $controller = new PostSingPassCallbackController;

        // Expect an exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        // Create the request
        $request = new Request(['code' => 'test-code', 'state' => 'test-state']);

        // Call the __invoke method
        $controller->__invoke($request, $singPassLoginMock);
    }

    protected function getPackageProviders($app): array
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
