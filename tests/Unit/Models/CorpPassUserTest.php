<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Models;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Models\CorpPassUser;
use Accredifysg\SingPassLogin\Tests\TestCase;

class CorpPassUserTest extends TestCase
{
    public function test_from_payload_with_full_claims(): void
    {
        $payload = [
            'sub' => '200000001A',
            'sub_attributes' => [
                'entity_type' => 'UEN',
                'entity_reg_number' => '200000001A',
                'entity_coi' => 'SG',
                'entity_name' => 'Test Corp Pte Ltd',
                'entity_uen_status' => 'Active',
            ],
            'act' => [
                'sub' => 'actor-uuid-123',
                'sub_attributes' => [
                    'account_type' => 'User',
                    'identity_number' => 'S1234567A',
                    'identity_coi' => 'SG',
                    'name' => 'John Doe',
                    'corppass_email' => 'john@testcorp.com',
                    'corppass_email_verified' => true,
                ],
            ],
        ];

        $user = CorpPassUser::fromPayload($payload);

        $this->assertEquals('200000001A', $user->entityId);
        $this->assertEquals('UEN', $user->entityType);
        $this->assertEquals('200000001A', $user->entityRegNumber);
        $this->assertEquals('SG', $user->entityCoi);
        $this->assertEquals('Test Corp Pte Ltd', $user->entityName);
        $this->assertEquals('Active', $user->entityUenStatus);

        $this->assertEquals('actor-uuid-123', $user->actorId);
        $this->assertEquals('User', $user->accountType);
        $this->assertEquals('S1234567A', $user->identityNumber);
        $this->assertEquals('SG', $user->identityCoi);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@testcorp.com', $user->corppassEmail);
        $this->assertTrue($user->corppassEmailVerified);
    }

    public function test_from_payload_with_minimal_claims(): void
    {
        $payload = [
            'sub' => '200000001A',
            'act' => [
                'sub' => 'actor-uuid-123',
            ],
        ];

        $user = CorpPassUser::fromPayload($payload);

        $this->assertEquals('200000001A', $user->entityId);
        $this->assertNull($user->entityType);
        $this->assertNull($user->entityName);

        $this->assertEquals('actor-uuid-123', $user->actorId);
        $this->assertNull($user->identityNumber);
        $this->assertNull($user->corppassEmail);
        $this->assertNull($user->corppassEmailVerified);
    }

    public function test_from_payload_throws_when_sub_is_missing(): void
    {
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Sub (entity ID) is empty');

        CorpPassUser::fromPayload([
            'act' => ['sub' => 'actor-uuid'],
        ]);
    }

    public function test_from_payload_throws_when_act_sub_is_missing(): void
    {
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Act sub (actor ID) is empty');

        CorpPassUser::fromPayload([
            'sub' => '200000001A',
        ]);
    }

    public function test_from_payload_throws_when_act_sub_is_empty(): void
    {
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Act sub (actor ID) is empty');

        CorpPassUser::fromPayload([
            'sub' => '200000001A',
            'act' => ['sub' => ''],
        ]);
    }

    public function test_email_verified_casts_to_bool(): void
    {
        $payload = [
            'sub' => '200000001A',
            'act' => [
                'sub' => 'actor-uuid',
                'sub_attributes' => [
                    'corppass_email_verified' => false,
                ],
            ],
        ];

        $user = CorpPassUser::fromPayload($payload);
        $this->assertFalse($user->corppassEmailVerified);
    }
}
