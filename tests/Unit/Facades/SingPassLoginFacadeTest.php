<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Facades;

use Accredifysg\SingPassLogin\Facades\SingPassLoginFacade;
use Accredifysg\SingPassLogin\SingPassLogin;
use Jose\Component\KeyManagement\JWKFactory;
use PHPUnit\Framework\TestCase;

class SingPassLoginFacadeTest extends TestCase
{
    public function test_facade_calls_underlying_class(): void
    {
        $mock = $this->createMock(SingPassLogin::class);

        $mock->expects($this->once())
            ->method('handleCallback');

        SingPassLoginFacade::swap($mock);

        $dpopKey = JWKFactory::createECKey('P-256');
        SingPassLoginFacade::handleCallback('test-code', 'test-state', 'test-code-verifier', $dpopKey, 'test-client-id', 'https://example.com/callback');
    }
}
