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
            ->method('handleCallback');

        // Mock the redirect response
        $redirectMock = $this->createMock(RedirectResponse::class);
        Redirect::shouldReceive('intended')->once()->andReturn($redirectMock);

        // Create an instance of the controller
        $controller = new PostSingPassCallbackController();

        // Call the __invoke method
        $response = $controller->__invoke(new Request(), $singPassLoginMock);

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
            ->willThrowException(new \Exception('Test exception'));

        // Create an instance of the controller
        $controller = new PostSingPassCallbackController();

        // Expect an exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        // Call the __invoke method
        $controller->__invoke(new Request(), $singPassLoginMock);
    }

    protected function getPackageProviders($app)
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }
}
