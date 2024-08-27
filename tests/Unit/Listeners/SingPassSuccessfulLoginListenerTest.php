<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Listeners;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Listeners\SingPassSuccessfulLoginListener;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\Models\User;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\MockObject\Exception;

class SingPassSuccessfulLoginListenerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->loadLaravelMigrations();
        $this->artisan('migrate');
        // Migrate
        include_once __DIR__.'/../../../database/migrations/add_nric_to_users_table.php';
        (new \AddNricToUsers)->up();
    }

    /**
     * @throws Exception
     */
    public function testHandleWithExistingUser()
    {
        // Create a user
        $user = User::factory()->create(['nric' => '123456']);

        // Mock SingPassUser
        $singPassUser = $this->createMock(SingPassUser::class);
        $singPassUser->method('getNric')->willReturn('123456');

        // Create the event
        $event = new SingPassSuccessfulLoginEvent($singPassUser);

        // Create the listener
        $listener = new SingPassSuccessfulLoginListener;

        // Call the handle method
        $response = $listener->handle($event);

        // Assert that the user is logged in
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());

        // Assert that we get a redirect response
        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /**
     * @throws Exception
     */
    public function testHandleWithNonExistentUser()
    {
        // Mock SingPassUser
        $singPassUser = $this->createMock(SingPassUser::class);
        $singPassUser->method('getNric')->willReturn('nonexistent');

        // Create the event
        $event = new SingPassSuccessfulLoginEvent($singPassUser);

        // Create the listener
        $listener = new SingPassSuccessfulLoginListener;

        // Expect an exception
        $this->expectException(ModelNotFoundException::class);

        // Call the handle method
        $listener->handle($event);
    }

    protected function getPackageProviders($app)
    {
        return [
            SingPassLoginServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
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
