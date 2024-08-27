<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Facades;

use Accredifysg\SingPassLogin\Facades\SingPassLoginFacade;
use Accredifysg\SingPassLogin\SingPassLogin;
use PHPUnit\Framework\TestCase;

class SingPassLoginFacadeTest extends TestCase
{
    public function testFacadeCallsUnderlyingClass()
    {
        $mock = $this->createMock(SingPassLogin::class);

        $mock->expects($this->once())
            ->method('handleCallback');

        SingPassLoginFacade::swap($mock);

        $result = SingPassLoginFacade::handleCallback();

        $this->assertEquals(null, $result);
    }
}
