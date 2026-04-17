<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Models;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Support\TypeNarrow;

readonly class CorpPassUser
{
    public function __construct(
        public string $entityId,
        public string $actorId,
        public ?string $entityType = null,
        public ?string $entityRegNumber = null,
        public ?string $entityCoi = null,
        public ?string $entityName = null,
        public ?string $entityUenStatus = null,
        public ?string $accountType = null,
        public ?string $identityNumber = null,
        public ?string $identityCoi = null,
        public ?string $name = null,
        public ?string $corppassEmail = null,
        public ?bool $corppassEmailVerified = null,
    ) {}

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
        $entityId = TypeNarrow::nonEmptyString($payload, 'sub')
            ?? throw new JwtPayloadException(400, 'Sub (entity ID) is empty or invalid');

        $act = $payload['act'] ?? [];
        if (! is_array($act)) {
            throw new JwtPayloadException(400, 'act claim must be an object');
        }

        $actorId = TypeNarrow::nonEmptyString($act, 'sub')
            ?? throw new JwtPayloadException(400, 'Act sub (actor ID) is empty or invalid');

        $entityAttributes = $payload['sub_attributes'] ?? [];
        if (! is_array($entityAttributes)) {
            throw new JwtPayloadException(400, 'sub_attributes must be an object');
        }

        $actorAttributes = $act['sub_attributes'] ?? [];
        if (! is_array($actorAttributes)) {
            throw new JwtPayloadException(400, 'act.sub_attributes must be an object');
        }

        return new self(
            entityId: $entityId,
            actorId: $actorId,
            entityType: TypeNarrow::optionalString($entityAttributes['entity_type'] ?? null),
            entityRegNumber: TypeNarrow::optionalString($entityAttributes['entity_reg_number'] ?? null),
            entityCoi: TypeNarrow::optionalString($entityAttributes['entity_coi'] ?? null),
            entityName: TypeNarrow::optionalString($entityAttributes['entity_name'] ?? null),
            entityUenStatus: TypeNarrow::optionalString($entityAttributes['entity_uen_status'] ?? null),
            accountType: TypeNarrow::optionalString($actorAttributes['account_type'] ?? null),
            identityNumber: TypeNarrow::optionalString($actorAttributes['identity_number'] ?? null),
            identityCoi: TypeNarrow::optionalString($actorAttributes['identity_coi'] ?? null),
            name: TypeNarrow::optionalString($actorAttributes['name'] ?? null),
            corppassEmail: TypeNarrow::optionalString($actorAttributes['corppass_email'] ?? null),
            corppassEmailVerified: TypeNarrow::optionalBool($actorAttributes['corppass_email_verified'] ?? null),
        );
    }
}
