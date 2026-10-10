<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Support\Money;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use InvalidArgumentException;

/**
 * Amount input showing "1 200,50" while the model keeps int minor units.
 */
final class MoneyInput
{
    /**
     * @param  bool  $positive  For rates and prices. Zero-value work is non-billable, never a zero price.
     */
    public static function make(string $name, bool $positive = false): TextInput
    {
        return TextInput::make($name)
            ->inputMode('decimal')
            ->mask(RawJs::make("\$money(\$input, ',', ' ', 2)"))
            ->rule('regex:/^[\d\s]+([.,]\d{1,2})?$/u')
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    if (filled($value) && Money::parse((string) $value) === 0) {
                        $fail(__('Enter an amount above zero. Use non-billable pricing for unpaid work.'));
                    }
                } catch (InvalidArgumentException) {
                    // The regex rule reports the format.
                }
            }, $positive)
            ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::toDecimal($state))
            ->dehydrateStateUsing(fn (mixed $state): ?int => blank($state) ? null : Money::parse((string) $state));
    }
}
