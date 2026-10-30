<?php

namespace App\Support;

class IdentityNumber
{
    public const PATTERN = '/^[0-9]{3}-?[0-9]{7}-?[0-9]$/D';

    public static function normalize(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1 ? str_replace('-', '', $value) : null;
    }

    public static function matches(mixed $candidate, string $expected): bool
    {
        $normalized = self::normalize($candidate);

        return $normalized !== null && hash_equals($expected, $normalized);
    }
}
