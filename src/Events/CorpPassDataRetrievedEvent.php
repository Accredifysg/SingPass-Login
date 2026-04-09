<?php

namespace Accredifysg\SingPassLogin\Events;

readonly class CorpPassDataRetrievedEvent
{
    /**
     * @param  array<string, mixed>  $corpPassData  Authorization data from the UserInfo endpoint (auth_info, tp_auth_info)
     */
    public function __construct(
        public array $corpPassData,
        public string $state,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getCorpPassData(): array
    {
        return $this->corpPassData;
    }

    public function getState(): string
    {
        return $this->state;
    }
}
