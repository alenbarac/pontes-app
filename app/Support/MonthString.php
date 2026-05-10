<?php

namespace App\Support;

/**
 * Tiny helper around the YYYY-MM month strings used across invoice flows
 * (generation, bulk slip download, bulk slip e-mail).
 */
final class MonthString
{
    /**
     * Pattern for `Illuminate\Validation` rules and frontend regex tests.
     */
    public const REGEX = '/^\d{4}-\d{2}$/';

    /**
     * Parse a "YYYY-MM" string into [$year, $month] or null when invalid.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function parse(string $month): ?array
    {
        if (preg_match(self::REGEX, $month, $matches) !== 1) {
            return null;
        }

        $year = (int) substr($month, 0, 4);
        $mon = (int) substr($month, 5, 2);

        if ($mon < 1 || $mon > 12 || $year < 1970 || $year > 2100) {
            return null;
        }

        return [$year, $mon];
    }

    public static function isValid(string $month): bool
    {
        return self::parse($month) !== null;
    }
}
