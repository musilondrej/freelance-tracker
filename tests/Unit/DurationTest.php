<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Duration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
    #[DataProvider('validDurations')]
    public function test_parses_durations(string $input, int $seconds): void
    {
        $this->assertSame($seconds, Duration::parse($input));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validDurations(): array
    {
        return [
            'H:MM' => ['1:30', 5400],
            'hours and minutes, any case' => ['2H15M', 8100],
            'decimal comma with unit' => ['1,5h', 5400],
            'bare hours' => ['3', 10_800],
        ];
    }

    #[DataProvider('invalidDurations')]
    public function test_rejects_invalid_or_non_positive_durations(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::parse($input);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDurations(): array
    {
        return [
            'empty' => [''],
            'zero' => ['0'],
            'garbage' => ['abc'],
            'minutes over 59' => ['1:75'],
            'negative' => ['-1'],
        ];
    }

    #[DataProvider('formattedDurations')]
    public function test_formats_seconds_as_hours_and_minutes(?int $seconds, string $expected): void
    {
        $this->assertSame($expected, Duration::format($seconds));
    }

    /**
     * @return array<string, array{?int, string}>
     */
    public static function formattedDurations(): array
    {
        return [
            'null' => [null, '0:00'],
            'under a minute truncates' => [59, '0:00'],
            'two-digit hours' => [36_060, '10:01'],
        ];
    }
}
