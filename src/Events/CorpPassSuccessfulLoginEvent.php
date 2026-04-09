<?php

namespace Accredifysg\SingPassLogin\Events;

use Accredifysg\SingPassLogin\Models\CorpPassUser;

class CorpPassSuccessfulLoginEvent
{
    public function __construct(
        public readonly CorpPassUser $user,
        public readonly string $state,
    ) {}

    public function getCorpPassUser(): CorpPassUser
    {
        return $this->user;
    }

    public function getState(): string
    {
        return $this->state;
    }
}
