<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Listeners;

use Accredifysg\SingPassLogin\Events\CorpPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Exceptions\CorpPassLoginException;
use Accredifysg\SingPassLogin\Listeners\CorpPassSuccessfulLoginListener;
use Accredifysg\SingPassLogin\Models\CorpPassUser;
use Accredifysg\SingPassLogin\Models\User;
use Accredifysg\SingPassLogin\SingPassLoginServiceProvider;
use Accredifysg\SingPassLogin\Tests\TestCase;
use AddCorppassEntityIdToUsers;
use AddNricToUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\MockObject\Exception;

class CorpPassSuccessfulLoginListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadLaravelMigrations();
        $this->artisan('migrate');
        include_once __DIR__.'/../../../database/migrations/add_nric_to_users_table.php';
        (new AddNricToUsers)->up();
        include_once __DIR__.'/../../../database/migrations/add_corppass_entity_id_to_users_table.php';
        (new AddCorppassEntityIdToUsers)->up();
    }

    /**
     * @throws Exception
     */
    public function test_handle_with_existing_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'corppass_entity_id' => 'uen-123',
            'nric' => 'S1234567D',
        ]);

        $corpPassUser = $this->createMock(CorpPassUser::class);
        $corpPassUser->method('getEntityId')->willReturn('uen-123');
        $corpPassUser->method('getIdentityNumber')->willReturn('S1234567D');

        $event = new CorpPassSuccessfulLoginEvent($corpPassUser, '9d8c5c0e-4f3a-4b2d-9e1f-0a1b2c3d4e5f');

        $listener = new CorpPassSuccessfulLoginListener;
        $listener->handle($event);

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->getKey(), Auth::id());
    }

    public function test_handle_with_non_existent_user(): void
    {
        $corpPassUser = $this->createMock(CorpPassUser::class);
        $corpPassUser->method('getEntityId')->willReturn('unknown-entity');
        $corpPassUser->method('getIdentityNumber')->willReturn('S9999999Z');

        $event = new CorpPassSuccessfulLoginEvent($corpPassUser, '9d8c5c0e-4f3a-4b2d-9e1f-0a1b2c3d4e5f');

        $listener = new CorpPassSuccessfulLoginListener;

        $this->expectException(CorpPassLoginException::class);

        $listener->handle($event);
    }

    public function test_handle_throws_exception_with_message(): void
    {
        $corpPassUser = new CorpPassUser('entity-x', 'actor-y');

        $event = new CorpPassSuccessfulLoginEvent($corpPassUser, '9d8c5c0e-4f3a-4b2d-9e1f-0a1b2c3d4e5f');

        $listener = new CorpPassSuccessfulLoginListener;

        $this->expectException(CorpPassLoginException::class);
        $this->expectExceptionMessage('This CorpPass account is not connected with any existing accounts in our system.');

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
