<?php

declare(strict_types=1);

namespace Accredifysg\SingPassLogin\Support;

final class TypeNarrow
{
    /**
     * Extract a non-empty string from an array by key, or return null.
     *
     * @param  array<mixed>  $data
     */
    public static function nonEmptyString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Narrow mixed to ?string (null if not a string).
     */
    public static function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Narrow mixed to ?bool with loose truthy handling (ints 0/1, strings "true"/"false").
     */
    public static function optionalBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1) {
            return $value === 1;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true') {
                return true;
            }
            if ($lower === 'false') {
                return false;
            }
        }

        return null;
    }

    /**
     * Narrow an array with potentially int keys to array<string, mixed>.
     * Returns null if any key is not a string.
     *
     * @param  array<mixed>  $array
     * @return array<string, mixed>|null
     */
    public static function stringKeyedArray(array $array): ?array
    {
        $out = [];
        foreach ($array as $key => $value) {
            if (! is_string($key)) {
                return null;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
