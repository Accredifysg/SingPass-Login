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
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\MockObject\MockObject;

class GetSingPassCallbackControllerTest extends TestCase
{
    public function test_invoke_calls_handle_callback_and_redirects(): void
    {
        // Create a mock of SingPassLogin using PHPUnit's mocking
        /** @var SingPassLogin|MockObject $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->once())
            ->method('handleCallback')
            ->with('test-code', 'test-state', 'test-code-verifier');

        // Mock the redirect response
        $redirectMock = $this->createMock(RedirectResponse::class);
        Redirect::shouldReceive('intended')->once()->andReturn($redirectMock);

        // Create an instance of the controller
        $controller = new GetSingPassCallbackController;

        // Create the request
        $request = new Request(['code' => 'test-code', 'state' => 'test-state'], [], [], ['code_verifier' => 'test-code-verifier']);

        // Call the __invoke method
        $response = $controller->__invoke($request, $singPassLoginMock);

        // Assert that the response is a RedirectResponse
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function test_sing_pass_login_exception_render(): void
    {
        Route::get('/login')->name('login');

        // Mock the SingPassLogin class
        $singPassLoginMock = $this->createMock(SingPassLogin::class);

        $singPassLoginMock->expects($this->once())
            ->method('handleCallback')
            ->with('test-code', 'test-state', 'test-code-verifier')
            ->willThrowException(new SingPassLoginException);

        // Create an instance of the controller
        $controller = new GetSingPassCallbackController;

        // Create the request
        $request = new Request(['code' => 'test-code', 'state' => 'test-state'], [], [], ['code_verifier' => 'test-code-verifier']);

        // Call the method and capture the response
        $response = $controller->__invoke($request, $singPassLoginMock);

        // Assert that the response is a redirect
        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Assert that it redirects back
        $this->assertEquals(route('login'), $response->getTargetUrl());

        // Assert that the session contains the expected error message
        $this->assertEquals([
            'singpass' => [
                [
                    'title' => 'No Account Found',
                    'description' => 'This SingPass account is not connected with any existing accounts in our system.',
                ],
            ],
        ], session('errors')->getBag('default')->messages());
    }

    public function test_missing_required_parameters_throws_exception(): void
    {
        Route::get('/login')->name('login');

        // Create an instance of the controller
        $controller = new GetSingPassCallbackController;

        // Create the request with missing parameters
        $request = new Request([], [], [], []); // Empty request with no parameters

        // Create a mock of SingPassLogin (though it shouldn't be called)
        /** @var SingPassLogin|MockObject $singPassLoginMock */
        $singPassLoginMock = $this->createMock(SingPassLogin::class);
        $singPassLoginMock->expects($this->never())->method('handleCallback');

        // Call the method and capture the response
        $response = $controller->__invoke($request, $singPassLoginMock);

        // Assert that the response is a redirect
        $this->assertInstanceOf(RedirectResponse::class, $response);

        // Assert that it redirects to login
        $this->assertEquals(route('login'), $response->getTargetUrl());

        // Assert that the session contains the expected error message
        $this->assertEquals([
            'singpass' => [
                [
                    'title' => 'Request Error',
                    'description' => 'An error has occurred when processing your request.',
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
