<?php

namespace App\Support;

/** Prevents spreadsheet formula injection: cells starting with = + - @ (or tab/CR) are prefixed with an apostrophe. */
final class CsvSafe
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    public static function row(array $values): array
    {
        return array_map([self::class, 'cell'], $values);
    }
}
