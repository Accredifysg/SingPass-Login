<?php

namespace Accredifysg\SingPassLogin\Tests;

use Accredifysg\SingPassLogin\Facades\SingPassLoginFacade;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;

#[WithMigration]
class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'SingPassLogin' => SingPassLoginFacade::class,
        ];
    }
}
