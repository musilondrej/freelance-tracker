<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Currency;
use App\Support\Money;
use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('durationValues')]
    public function test_values_a_duration_at_an_hourly_rate_rounding_half_up(int $seconds, int $rate, int $expected): void
    {
        $this->assertSame($expected, Money::roundHalfUpDiv($seconds * $rate, 3600));
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function durationValues(): array
    {
        return [
            'zero' => [0, 120_000, 0],
            '1:30 × 1 200 Kč' => [5400, 120_000, 180_000],
            '20 min × 1 000 Kč rounds down' => [1200, 100_000, 33_333],
            '40 min × 1 000 Kč rounds up' => [2400, 100_000, 66_667],
            '17 s × 1 Kč below half' => [17, 100, 0],
            '18 s × 1 Kč exactly half rounds up' => [18, 100, 1],
            '30 days × 100 000 Kč no overflow' => [2_592_000, 10_000_000, 7_200_000_000],
        ];
    }

    #[DataProvider('parsedAmounts')]
    public function test_parses_user_input_into_minor_units_without_floats(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function parsedAmounts(): array
    {
        return [
            'whole' => ['1200', 120_000],
            'space and comma' => ['1 200,50', 120_050],
            'decimal point, one digit' => ['1200.5', 120_050],
            'one haléř' => ['0,01', 1],
            'non-breaking space and padding' => ["  1\u{00A0}000 ", 100_000],
        ];
    }

    /**
     * @param  Closure(): mixed  $call
     */
    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_input(Closure $call): void
    {
        $this->expectException(InvalidArgumentException::class);

        $call();
    }

    /**
     * @return array<string, array{Closure(): mixed}>
     */
    public static function invalidInputs(): array
    {
        return [
            'negative numerator' => [fn (): int => Money::roundHalfUpDiv(-1, 3600)],
            'zero denominator' => [fn (): int => Money::roundHalfUpDiv(1, 0)],
            'empty amount' => [fn (): int => Money::parse('')],
            'garbage amount' => [fn (): int => Money::parse('abc')],
            'three decimals' => [fn (): int => Money::parse('1.234')],
            'negative amount' => [fn (): int => Money::parse('-5')],
            'two separators' => [fn (): int => Money::parse('1,2,3')],
        ];
    }

    public function test_formats_amounts(): void
    {
        $this->assertSame('1200,50', Money::toDecimal(120_050));
        $this->assertSame('0,05', Money::toDecimal(5));
        $this->assertSame("1\u{00A0}201\u{00A0}Kč", Money::format(120_050, Currency::CZK));
        $this->assertSame("1\u{00A0}200,50\u{00A0}Kč", Money::format(120_050, Currency::CZK, precision: 2));
        $this->assertSame("60\u{00A0}€", Money::format(6_000, Currency::EUR));
        $this->assertSame("-1\u{00A0}201\u{00A0}Kč", Money::format(-120_050, Currency::CZK));
    }
}
