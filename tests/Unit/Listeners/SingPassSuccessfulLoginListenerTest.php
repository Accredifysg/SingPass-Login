<?php

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
use PHPUnit\Framework\MockObject\Exception;

class SingPassSuccessfulLoginListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadLaravelMigrations();
        $this->artisan('migrate');
        // Migrate
        include_once __DIR__.'/../../../database/migrations/add_nric_to_users_table.php';
        (new AddNricToUsers)->up();
    }

    /**
     * @throws Exception
     */
    public function test_handle_with_existing_user(): void
    {
        // Create a user
        /** @var User $user */
        $user = User::factory()->create(['nric' => '123456']);

        // Mock SingPassUser
        $singPassUser = $this->createMock(SingPassUser::class);
        $singPassUser->method('getNric')->willReturn('123456');

        // Create the event
        $event = new SingPassSuccessfulLoginEvent($singPassUser, 'LOGIN-');

        // Create the listener
        $listener = new SingPassSuccessfulLoginListener;

        // Call the handle method
        $listener->handle($event);

        // Assert that the user is logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->getKey(), Auth::id());
    }

    /**
     * @throws Exception
     */
    public function test_handle_with_non_existent_user(): void
    {
        // Mock SingPassUser
        $singPassUser = $this->createMock(SingPassUser::class);
        $singPassUser->method('getNric')->willReturn('nonexistent');

        // Create the event
        $event = new SingPassSuccessfulLoginEvent($singPassUser, 'LOGIN-');

        // Create the listener
        $listener = new SingPassSuccessfulLoginListener;

        // Expect an exception
        $this->expectException(SingPassLoginException::class);

        // Call the handle method
        $listener->handle($event);
    }

    public function test_handle_with_update_to_existent_user(): void
    {
        // Create a user
        /** @var User $user */
        $user = User::factory()->create(['nric' => '123456']);

        // Mock SingPassUser
        $singPassUser = $this->createMock(SingPassUser::class);
        $singPassUser->method('getNric')->willReturn('123456');

        // Create the event
        $event = new SingPassSuccessfulLoginEvent($singPassUser, 'ENABLE-');

        // Create the listener
        $listener = new SingPassSuccessfulLoginListener;

        // Call the handle method
        $listener->handle($event);

        // Assert that the user is logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->getKey(), Auth::id());
    }

    public function test_handle_throws_exception(): void
    {
        $singpassUser = new SingPassUser('1111-1111-1111-1111', 'S0000000A');
        $event = new SingPassSuccessfulLoginEvent($singpassUser, 'LOGIN-');

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
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
