<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Events;

use Accredifysg\SingPassLogin\Events\CorpPassSuccessfulLoginEvent;
use Accredifysg\SingPassLogin\Models\CorpPassUser;
use Accredifysg\SingPassLogin\Tests\TestCase;

class CorpPassSuccessfulLoginEventTest extends TestCase
{
    public function test_event_carries_user_and_state(): void
    {
        $user = new CorpPassUser(
            entityId: '200000001A',
            actorId: 'actor-uuid',
        );

        $event = new CorpPassSuccessfulLoginEvent($user, 'test-state');

        $this->assertSame($user, $event->getCorpPassUser());
        $this->assertEquals('test-state', $event->getState());
    }
}
