<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Models;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Support\TypeNarrow;

readonly class SingPassUser
{
    public function __construct(
        public string $uuid,
        public ?string $nric = null,
        public ?string $accountType = null,
        public ?string $identityCoi = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $mobileNo = null,
    ) {}

    /**
     * Create a SingPassUser from a decoded ID token payload.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JwtPayloadException
     */
    public static function fromPayload(array $payload): self
    {
        $sub = TypeNarrow::nonEmptyString($payload, 'sub')
            ?? throw new JwtPayloadException(400, 'Sub is empty or invalid');

        $subAttributes = $payload['sub_attributes'] ?? [];
        if (! is_array($subAttributes)) {
            throw new JwtPayloadException(400, 'sub_attributes must be an object');
        }

        return new self(
            uuid: $sub,
            nric: TypeNarrow::optionalString($subAttributes['identity_number'] ?? null),
            accountType: TypeNarrow::optionalString($subAttributes['account_type'] ?? null),
            identityCoi: TypeNarrow::optionalString($subAttributes['identity_coi'] ?? null),
            name: TypeNarrow::optionalString($subAttributes['name'] ?? null),
            email: TypeNarrow::optionalString($subAttributes['email'] ?? null),
            mobileNo: TypeNarrow::optionalString($subAttributes['mobileno'] ?? null),
        );
    }
}
