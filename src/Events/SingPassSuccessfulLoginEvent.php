<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Events;

use Accredifysg\SingPassLogin\Models\SingPassUser;

/**
 * Class SingPassSuccessfulLoginEvent
 */
class SingPassSuccessfulLoginEvent
{
    /**
     * The SingPass user.
     */
    public SingPassUser $user;

    public string $state;

    /**
     * SingPassSuccessfulLoginEvent constructor.
     */
    public function __construct(SingPassUser $user, string $state)
    {
        $this->user = $user;
        $this->state = $state;
    }

    /**
     * Get the user represented in the SingPass sign in attempt
     *
     * @return SingPassUser The user for the SingPassSuccessfulLoginEvent event
     */
    public function getSingPassUser(): SingPassUser
    {
        return $this->user;
    }

    public function getState(): string
    {
        return $this->state;
    }
}
