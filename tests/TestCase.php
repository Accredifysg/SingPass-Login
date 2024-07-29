<?php

namespace Accredifysg\SingPassLogin\Tests;

use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithWorkbench;

#[WithMigration]
class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;

    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'SingPassLogin' => 'Accredifysg\SingPass-Login\Facades\SingPassLoginFacade',
        ];
    }
}
