<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Events;

/**
 * Class MyInfoDataRetrievedEvent
 */
readonly class MyInfoDataRetrievedEvent
{
    /**
     * MyInfoDataRetrievedEvent constructor.
     *
     * @param  array<string, mixed>  $myInfoData
     */
    public function __construct(
        public array $myInfoData,
        public string $state
    ) {}

    /**
     * Get the MyInfo data retrieved from the UserInfo endpoint
     *
     * @return array<string, mixed> The MyInfo data
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
