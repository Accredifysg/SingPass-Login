<?php

namespace Accredifysg\SingPassLogin\Events;

use Accredifysg\SingPassLogin\Models\SingPassUser;

/**
 * Class MyInfoDataRetrievedEvent
 */
readonly class MyInfoDataRetrievedEvent
{
    /**
     * MyInfoDataRetrievedEvent constructor.
     */
    public function __construct(
        public array $myInfoData,
        public string $state
    ) {}

    /**
     * Get the MyInfo data retrieved from the UserInfo endpoint
     *
     * @return array The MyInfo data
     */
    public function getMyInfoData(): array
    {
        return $this->myInfoData;
    }

    /**
     * Get the state parameter from the OAuth flow
     *
     * @return string The state parameter
     */
    public function getState(): string
    {
        return $this->state;
    }
}
