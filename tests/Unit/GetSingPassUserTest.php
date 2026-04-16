<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit;

use Accredifysg\SingPassLogin\Exceptions\JwtPayloadException;
use Accredifysg\SingPassLogin\Models\SingPassUser;
use Accredifysg\SingPassLogin\Tests\TestCase;

class GetSingPassUserTest extends TestCase
{
    public function test_from_payload_with_sub_attributes(): void
    {
        $payload = [
            'sub' => '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9',
            'sub_attributes' => [
                'identity_number' => 'S8829314B',
                'account_type' => 'standard',
                'identity_coi' => 'SG',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'mobileno' => '91234567',
            ],
        ];

        $singPassUser = SingPassUser::fromPayload($payload);

        $this->assertInstanceOf(SingPassUser::class, $singPassUser);
        $this->assertEquals('1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9', $singPassUser->getUuid());
        $this->assertEquals('S8829314B', $singPassUser->getNric());
        $this->assertEquals('standard', $singPassUser->getAccountType());
        $this->assertEquals('SG', $singPassUser->getIdentityCoi());
        $this->assertEquals('John Doe', $singPassUser->getName());
        $this->assertEquals('john@example.com', $singPassUser->getEmail());
        $this->assertEquals('91234567', $singPassUser->getMobileNo());
    }

    public function test_from_payload_uuid_only(): void
    {
        $payload = [
            'sub' => '1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9',
        ];

        $singPassUser = SingPassUser::fromPayload($payload);

        $this->assertInstanceOf(SingPassUser::class, $singPassUser);
        $this->assertEquals('1c0cee38-3a8f-4f8a-83bc-7a0e4c59d6a9', $singPassUser->getUuid());
        $this->assertNull($singPassUser->getNric());
        $this->assertNull($singPassUser->getAccountType());
        $this->assertNull($singPassUser->getIdentityCoi());
        $this->assertNull($singPassUser->getName());
        $this->assertNull($singPassUser->getEmail());
        $this->assertNull($singPassUser->getMobileNo());
    }

    public function test_from_payload_foreign_account(): void
    {
        $payload = [
            'sub' => '7c9c72ec-5be2-495a-a78e-61e809a2a236',
            'sub_attributes' => [
                'identity_number' => 'K28394589',
                'account_type' => 'foreign',
                'identity_coi' => 'TK',
                'name' => 'Larry Doe',
                'email' => 'larrydoe@gmail.com',
            ],
        ];

        $singPassUser = SingPassUser::fromPayload($payload);

        $this->assertInstanceOf(SingPassUser::class, $singPassUser);
        $this->assertEquals('7c9c72ec-5be2-495a-a78e-61e809a2a236', $singPassUser->getUuid());
        $this->assertEquals('K28394589', $singPassUser->getNric());
        $this->assertEquals('foreign', $singPassUser->getAccountType());
        $this->assertEquals('TK', $singPassUser->getIdentityCoi());
        $this->assertEquals('Larry Doe', $singPassUser->getName());
        $this->assertEquals('larrydoe@gmail.com', $singPassUser->getEmail());
        $this->assertNull($singPassUser->getMobileNo());
    }

    public function test_from_payload_empty_sub(): void
    {
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Sub is empty');

        SingPassUser::fromPayload(['sub' => '']);
    }

    public function test_from_payload_missing_sub(): void
    {
        $this->expectException(JwtPayloadException::class);
        $this->expectExceptionMessage('Sub is empty');

        SingPassUser::fromPayload([]);
    }
}
