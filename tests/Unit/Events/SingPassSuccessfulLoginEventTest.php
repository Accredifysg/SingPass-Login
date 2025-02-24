<?php

namespace Accredifysg\SingPassLogin\Tests\Unit\Events;

use Accredifysg\SingPassLogin\Events\SingPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\Tests\TestCase;

class SingPassSuccessfulLoginEventTest extends TestCase
{
    public function test_event_instantiation(): void
    {
        // Create a mock SingPassUser object
        $user = new SingPassUser('test-uuid', 'test-nric');

        // Instantiate the event with the mock user
        $event = new SingPassSuccessfulLoginEvent($user, 'LOGIN-');

        // Assert that the event's user is the same as the mock user
        $this->assertSame($user, $event->getSingPassUser());
    }

    public function test_get_singpass_user(): void
    {
        // Create a mock SingPassUser object
        $user = new SingPassUser('test-uuid', 'test-nric');

        // Instantiate the event with the mock user
        $event = new SingPassSuccessfulLoginEvent($user, 'LOGIN-');

        // Call the getSingPassUser method and assert the returned user is the same as the mock user
        $this->assertSame($user, $event->getSingPassUser());
    }
}
