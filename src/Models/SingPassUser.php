<?php

namespace Accredifysg\SingPassLogin\Models;

class SingPassUser
{
    /**
     * The UUID of the user returned by SingPass
     *
     * @var string
     */
    protected string $uuid;

    /**
     * The NRIC of the user returned by SingPass
     *
     * @var string
     */
    protected string $nric;

    /**
     * SingPassUser Constructor
     *
     * @param string $uuid
     * @param string $nric
     */
    public function __construct(string $uuid, string $nric)
    {
        $this->uuid = $uuid;
        $this->nric = $nric;
    }

    /**
     * Gets the user's UUID
     *
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Gets the user's NRIC
     *
     * @return string
     */
    public function getNric(): string
    {
        return $this->nric;
    }
}
