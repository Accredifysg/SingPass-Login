<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests;

use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Orchestra\Testbench\Attributes\WithMigration;
use RuntimeException;

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
     * @return array<mixed>
     */
    protected function configArray(string $key): array
    {
        $value = config($key);
        if (! is_array($value)) {
            $this->fail(sprintf('Config key "%s" must resolve to an array.', $key));
        }

        return $value;
    }

    protected function routeCollection(): RouteCollection
    {
        if ($this->app === null) {
            $this->fail('Application not bootstrapped.');
        }

        $routes = $this->app->make(Router::class)->getRoutes();
        if (! $routes instanceof RouteCollection) {
            $this->fail('Expected Laravel route collection.');
        }

        return $routes;
    }

    protected function appConfigSet(Application $app, string $key, mixed $value): void
    {
        $repository = $app->make('config');
        if (! $repository instanceof Repository) {
            throw new RuntimeException('Application must expose a config repository.');
        }
        $repository->set($key, $value);
    }
}
