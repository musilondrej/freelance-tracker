<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Currency;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Amounts are int minor units, like cents. Never a float.
 */
final class Money
{
    /**
     * Integer division rounded half up, used to turn hours and a rate into minor units.
     * The same rule is used by the generated `time_entries.amount` column.
     */
    public static function roundHalfUpDiv(int $numerator, int $denominator): int
    {
        if ($numerator < 0 || $denominator <= 0) {
            throw new InvalidArgumentException('Only non-negative numerators and positive denominators are supported.');
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    /** Minor units per hour, rounded half up. Null without time. */
    public static function perHour(int $amount, int $seconds): ?int
    {
        return $seconds > 0 ? self::roundHalfUpDiv($amount * 3600, $seconds) : null;
    }

    /**
     * Parses user input such as "1200", "1 200,50" or "1200.5" into minor units without floats.
     */
    public static function parse(string $input): int
    {
        $normalized = Str::of($input)->trim()->remove([' ', "\u{00A0}"])->replace(',', '.')->value();

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid amount [{$input}].");
        }

        return (int) $matches[1] * 100 + (int) Str::padRight($matches[2] ?? '0', 2, '0');
    }

    /**
     * Minor units as a plain decimal string for forms and CSV, like "1200,50" for 120050.
     */
    public static function toDecimal(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        return sprintf('%s%d,%02d', $sign, intdiv($amount, 100), $amount % 100);
    }

    /**
     * Whole units in the app ("1 201 Kč"). Statements pass 2, because they must match the invoice to the cent.
     */
    public static function format(int $amount, Currency $currency, int $precision = 0): string
    {
        // Whole units round half up like the rest of the app; the formatter itself rounds half even.
        $value = $precision === 0
            ? self::roundHalfUpDivSigned($amount, 100)
            : $amount / 100; // Display only: float conversion cannot affect stored values.

        return (string) Number::currency($value, in: $currency->value, locale: 'cs', precision: $precision);
    }

    /** roundHalfUpDiv() for negative numerators too, like a budget overrun: rounds away from zero on .5. */
    private static function roundHalfUpDivSigned(int $numerator, int $denominator): int
    {
        return ($numerator < 0 ? -1 : 1) * self::roundHalfUpDiv(abs($numerator), $denominator);
    }
}
