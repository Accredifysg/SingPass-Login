<?php

namespace Accredifysg\SingPassLogin\Events;

use Accredifysg\SingPassLogin\Models\SingPassUser;
/**
 * Class SingPassSuccessfulLoginEvent
 *
 * @package Accredifysg\SingPassLogin\Events
 */
class SingPassSuccessfulLoginEvent
{
    /**
     * The SingPass user.
     *
     * @var SingPassUser
     */
    public SingPassUser $user;

    /**
     * SingPassSuccessfulLoginEvent constructor.
     *
     * @param SingPassUser $user
     */
    public function __construct(SingPassUser $user)
    {
        $this->user = $user;
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
}