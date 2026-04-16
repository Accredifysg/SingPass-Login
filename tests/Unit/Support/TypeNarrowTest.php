<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Tests\Unit\Support;

use Accredifysg\SingPassLogin\Support\TypeNarrow;
use Accredifysg\SingPassLogin\Tests\TestCase;

class TypeNarrowTest extends TestCase
{
    public function test_non_empty_string_returns_value_when_present(): void
    {
        $this->assertSame('hello', TypeNarrow::nonEmptyString(['k' => 'hello'], 'k'));
    }

    public function test_non_empty_string_returns_null_when_missing(): void
    {
        $this->assertNull(TypeNarrow::nonEmptyString([], 'k'));
    }

    public function test_non_empty_string_returns_null_when_empty_string(): void
    {
        $this->assertNull(TypeNarrow::nonEmptyString(['k' => ''], 'k'));
    }

    public function test_non_empty_string_returns_null_when_not_string(): void
    {
        $this->assertNull(TypeNarrow::nonEmptyString(['k' => 123], 'k'));
        $this->assertNull(TypeNarrow::nonEmptyString(['k' => true], 'k'));
    }

    public function test_optional_string_returns_string(): void
    {
        $this->assertSame('x', TypeNarrow::optionalString('x'));
        $this->assertSame('', TypeNarrow::optionalString(''));
    }

    public function test_optional_string_returns_null_for_non_strings(): void
    {
        $this->assertNull(TypeNarrow::optionalString(null));
        $this->assertNull(TypeNarrow::optionalString(0));
        $this->assertNull(TypeNarrow::optionalString([]));
    }

    public function test_optional_bool_null_returns_null(): void
    {
        $this->assertNull(TypeNarrow::optionalBool(null));
    }

    public function test_optional_bool_bools(): void
    {
        $this->assertTrue(TypeNarrow::optionalBool(true));
        $this->assertFalse(TypeNarrow::optionalBool(false));
    }

    public function test_optional_bool_int_zero_and_one(): void
    {
        $this->assertFalse(TypeNarrow::optionalBool(0));
        $this->assertTrue(TypeNarrow::optionalBool(1));
    }

    public function test_optional_bool_string_true_false(): void
    {
        $this->assertTrue(TypeNarrow::optionalBool('true'));
        $this->assertTrue(TypeNarrow::optionalBool('TRUE'));
        $this->assertFalse(TypeNarrow::optionalBool('false'));
        $this->assertFalse(TypeNarrow::optionalBool('FALSE'));
    }

    public function test_optional_bool_returns_null_for_unsupported_values(): void
    {
        $this->assertNull(TypeNarrow::optionalBool(2));
        $this->assertNull(TypeNarrow::optionalBool('yes'));
        $this->assertNull(TypeNarrow::optionalBool(''));
        $this->assertNull(TypeNarrow::optionalBool(1.0));
        $this->assertNull(TypeNarrow::optionalBool([]));
    }

    public function test_string_keyed_array_returns_copy_for_string_keys(): void
    {
        $in = ['a' => 1, 'b' => ['nested' => true]];
        $out = TypeNarrow::stringKeyedArray($in);

        $this->assertSame($in, $out);
    }

    public function test_string_keyed_array_returns_empty_for_empty_input(): void
    {
        $this->assertSame([], TypeNarrow::stringKeyedArray([]));
    }

    public function test_string_keyed_array_returns_null_when_key_is_integer(): void
    {
        $this->assertNull(TypeNarrow::stringKeyedArray([0 => 'x']));
    }

    public function test_string_keyed_array_returns_null_when_key_is_mixed_list(): void
    {
        $this->assertNull(TypeNarrow::stringKeyedArray(['ok' => 1, 1 => 'bad']));
    }
}
