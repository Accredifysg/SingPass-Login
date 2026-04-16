<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Models;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;

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

    /**
     * Create a SingPassUser from a decoded ID token payload.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JwtPayloadException
     */
    public static function fromPayload(array $payload): self
    {
        $sub = $payload['sub'] ?? '';
        if ($sub === '') {
            throw new JwtPayloadException(400, 'Sub is empty');
        }

        $subAttributes = $payload['sub_attributes'] ?? [];

        return new self(
            uuid: $sub,
            nric: $subAttributes['identity_number'] ?? null,
            accountType: $subAttributes['account_type'] ?? null,
            identityCoi: $subAttributes['identity_coi'] ?? null,
            name: $subAttributes['name'] ?? null,
            email: $subAttributes['email'] ?? null,
            mobileNo: $subAttributes['mobileno'] ?? null,
        );
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
