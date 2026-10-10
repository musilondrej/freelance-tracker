<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Durations are whole seconds. Input formats: "1:30", "90m", "2h", "2h 15m", "1.5h" and "1,5" for hours.
 */
final class Duration
{
    public static function parse(string $input): int
    {
        // Same normalising as Money::parse(), non-breaking spaces included.
        $value = Str::of($input)->remove([' ', "\u{00A0}"])->lower()->value();

        $seconds = match (true) {
            preg_match('/^(\d+):([0-5]\d)$/', $value, $m) === 1 => (int) $m[1] * 3600 + (int) $m[2] * 60,
            preg_match('/^(?:(\d+)h)?(?:(\d+)m)?$/', $value, $m) === 1 && $value !== '' => (int) ($m[1] ?? 0) * 3600 + (int) ($m[2] ?? 0) * 60,
            preg_match('/^(\d+)(?:[.,](\d{1,2}))?h?$/', $value, $m) === 1 => (int) $m[1] * 3600 + intdiv((int) Str::padRight($m[2] ?? '0', 2, '0') * 3600, 100),
            default => throw new InvalidArgumentException("Invalid duration [{$input}]."),
        };

        if ($seconds <= 0) {
            throw new InvalidArgumentException("Duration must be positive [{$input}].");
        }

        return $seconds;
    }

    /** Formats 5400 as "1:30". */
    public static function format(?int $seconds): string
    {
        $seconds ??= 0;

        return sprintf('%d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
