<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Support\Duration;
use Closure;
use Filament\Forms\Components\TextInput;
use InvalidArgumentException;

/**
 * Duration input that accepts "1:30", "90m", "2h 15m" or "1,5" hours. The model stores seconds.
 */
final class DurationInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->placeholder('1:30')
            ->helperText(__('E.g. 1:30, 90m, 2h 15m or 1,5'))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                try {
                    Duration::parse((string) $value);
                } catch (InvalidArgumentException) {
                    $fail(__('Enter a positive duration, e.g. 1:30 or 2h 15m.'));
                }
            })
            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Duration::format($state))
            ->dehydrateStateUsing(fn (mixed $state): ?int => blank($state) ? null : Duration::parse((string) $state));
    }
}
