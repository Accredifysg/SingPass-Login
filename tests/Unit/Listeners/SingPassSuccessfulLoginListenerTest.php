<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Listeners;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\SingPassLoginException;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\Models\User;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use AddNricToUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class SingPassSuccessfulLoginListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadLaravelMigrations();
        $this->artisan('migrate');
        include_once __DIR__.'/../../../database/migrations/add_nric_to_users_table.php';
        (new AddNricToUsers)->up();
    }

    public function test_handle_with_existing_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['nric' => '123456']);

        $singPassUser = new SingPassUser(uuid: 'test-uuid', nric: '123456');

        $event = new SingPassSuccessfulLoginEvent($singPassUser, '9d8c5c0e-4f3a-4b2d-9e1f-0a1b2c3d4e5f');

        $listener = new SingPassSuccessfulLoginListener;
        $listener->handle($event);

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->getKey(), Auth::id());
    }

    public function test_handle_with_non_existent_user(): void
    {
        $singPassUser = new SingPassUser(uuid: 'test-uuid', nric: 'nonexistent');

        $event = new SingPassSuccessfulLoginEvent($singPassUser, '9d8c5c0e-4f3a-4b2d-9e1f-0a1b2c3d4e5f');

        $listener = new SingPassSuccessfulLoginListener;

        $this->expectException(SingPassLoginException::class);

        $listener->handle($event);
    }

    public function test_handle_throws_exception(): void
    {
        $singpassUser = new SingPassUser('1111-1111-1111-1111', 'S0000000A');
        $event = new SingPassSuccessfulLoginEvent($singpassUser, '9d8c5c0e-4f3a-4b2d-9e1f-0a1b2c3d4e5f');

        $listener = new SingPassSuccessfulLoginListener;

        $this->expectException(SingPassLoginException::class);
        $this->expectExceptionMessage('This SingPass account is not connected with any existing accounts in our system.');

        $listener->handle($event);
    }

    protected function getPackageProviders($app): array
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
