<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Events;

use Accredifysg\SingPassLogin\Events\CorpPassDataRetrievedEvent;
use Accredifysg\SingPassLogin\Tests\TestCase;

class CorpPassDataRetrievedEventTest extends TestCase
{
    public function test_event_carries_data_and_state(): void
    {
        $data = [
            'auth_info' => ['role' => 'admin'],
            'tp_auth_info' => ['third_party' => 'data'],
        ];

        $event = new CorpPassDataRetrievedEvent($data, 'test-state');

        $this->assertEquals($data, $event->getCorpPassData());
        $this->assertEquals('test-state', $event->getState());
    }

    public function test_event_with_empty_data(): void
    {
        $event = new CorpPassDataRetrievedEvent([], 'test-state');

        $this->assertEquals([], $event->getCorpPassData());
    }
}
