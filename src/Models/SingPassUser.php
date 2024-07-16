<?php

namespace Accredifysg\SingPassLogin\Models;

class SingPassUser
{
    /**
     * The UUID of the user returned by SingPass
     */
    protected string $uuid;

    /**
     * The NRIC of the user returned by SingPass
     */
    protected string $nric;

    /**
     * SingPassUser Constructor
     */
    public function __construct(string $uuid, string $nric)
    {
        $this->uuid = $uuid;
        $this->nric = $nric;
    }

    /**
     * Gets the user's UUID
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Gets the user's NRIC
     */
    public function getNric(): string
    {
        return $this->nric;
    }
}
