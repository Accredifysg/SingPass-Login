<?php

namespace Accredifysg\SingPassLogin\Models;

class SingPassUser
{
    protected string $uuid;

    protected ?string $nric;

    protected ?string $accountType;

    protected ?string $identityCoi;

    protected ?string $name;

    protected ?string $email;

    protected ?string $mobileNo;

    public function __construct(
        string $uuid,
        ?string $nric = null,
        ?string $accountType = null,
        ?string $identityCoi = null,
        ?string $name = null,
        ?string $email = null,
        ?string $mobileNo = null,
    ) {
        $this->uuid = $uuid;
        $this->nric = $nric;
        $this->accountType = $accountType;
        $this->identityCoi = $identityCoi;
        $this->name = $name;
        $this->email = $email;
        $this->mobileNo = $mobileNo;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getNric(): ?string
    {
        return $this->nric;
    }

    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    public function getIdentityCoi(): ?string
    {
        return $this->identityCoi;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getMobileNo(): ?string
    {
        return $this->mobileNo;
    }
}
