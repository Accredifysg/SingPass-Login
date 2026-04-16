<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Models;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;

class CorpPassUser
{
    protected string $entityId;

    protected ?string $entityType;

    protected ?string $entityRegNumber;

    protected ?string $entityCoi;

    protected ?string $entityName;

    protected ?string $entityUenStatus;

    protected string $actorId;

    protected ?string $accountType;

    protected ?string $identityNumber;

    protected ?string $identityCoi;

    protected ?string $name;

    protected ?string $corppassEmail;

    protected ?bool $corppassEmailVerified;

    public function __construct(
        string $entityId,
        string $actorId,
        ?string $entityType = null,
        ?string $entityRegNumber = null,
        ?string $entityCoi = null,
        ?string $entityName = null,
        ?string $entityUenStatus = null,
        ?string $accountType = null,
        ?string $identityNumber = null,
        ?string $identityCoi = null,
        ?string $name = null,
        ?string $corppassEmail = null,
        ?bool $corppassEmailVerified = null,
    ) {
        $this->entityId = $entityId;
        $this->actorId = $actorId;
        $this->entityType = $entityType;
        $this->entityRegNumber = $entityRegNumber;
        $this->entityCoi = $entityCoi;
        $this->entityName = $entityName;
        $this->entityUenStatus = $entityUenStatus;
        $this->accountType = $accountType;
        $this->identityNumber = $identityNumber;
        $this->identityCoi = $identityCoi;
        $this->name = $name;
        $this->corppassEmail = $corppassEmail;
        $this->corppassEmailVerified = $corppassEmailVerified;
    }

    /**
     * Create a CorpPassUser from a decoded ID token payload.
     *
     * CorpPass tokens use a hierarchical structure:
     * - `sub` + `sub_attributes` for the entity (company/organisation)
     * - `act.sub` + `act.sub_attributes` for the acting user
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JwtPayloadException
     */
    public static function fromPayload(array $payload): self
    {
        $entityId = $payload['sub'] ?? '';
        if ($entityId === '') {
            throw new JwtPayloadException(400, 'Sub (entity ID) is empty');
        }

        $act = $payload['act'] ?? [];
        $actorId = $act['sub'] ?? '';
        if ($actorId === '') {
            throw new JwtPayloadException(400, 'Act sub (actor ID) is empty');
        }

        $entityAttributes = $payload['sub_attributes'] ?? [];
        $actorAttributes = $act['sub_attributes'] ?? [];

        return new self(
            entityId: $entityId,
            actorId: $actorId,
            entityType: $entityAttributes['entity_type'] ?? null,
            entityRegNumber: $entityAttributes['entity_reg_number'] ?? null,
            entityCoi: $entityAttributes['entity_coi'] ?? null,
            entityName: $entityAttributes['entity_name'] ?? null,
            entityUenStatus: $entityAttributes['entity_uen_status'] ?? null,
            accountType: $actorAttributes['account_type'] ?? null,
            identityNumber: $actorAttributes['identity_number'] ?? null,
            identityCoi: $actorAttributes['identity_coi'] ?? null,
            name: $actorAttributes['name'] ?? null,
            corppassEmail: $actorAttributes['corppass_email'] ?? null,
            corppassEmailVerified: isset($actorAttributes['corppass_email_verified'])
                ? (bool) $actorAttributes['corppass_email_verified']
                : null,
        );
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityRegNumber(): ?string
    {
        return $this->entityRegNumber;
    }

    public function getEntityCoi(): ?string
    {
        return $this->entityCoi;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function getEntityUenStatus(): ?string
    {
        return $this->entityUenStatus;
    }

    public function getActorId(): string
    {
        return $this->actorId;
    }

    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    public function getIdentityNumber(): ?string
    {
        return $this->identityNumber;
    }

    public function getIdentityCoi(): ?string
    {
        return $this->identityCoi;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCorppassEmail(): ?string
    {
        return $this->corppassEmail;
    }

    public function getCorppassEmailVerified(): ?bool
    {
        return $this->corppassEmailVerified;
    }
}
