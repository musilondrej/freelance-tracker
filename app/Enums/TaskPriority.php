<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Int-backed, so sorting by the column sorts by priority. */
enum TaskPriority: int implements HasColor, HasIcon, HasLabel
{
    case Low = 1;
    case Normal = 2;
    case High = 3;
    case Urgent = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Normal => __('Normal'),
            self::High => __('High'),
            self::Urgent => __('Urgent'),
        };
    }

    /** High and urgent stand out in lists and on cards; low and normal stay quiet. */
    public function standsOut(): bool
    {
        return $this->value >= self::High->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low, self::Normal => 'gray',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Low => Heroicon::OutlinedChevronDown,
            self::Normal => Heroicon::OutlinedMinus,
            self::High => Heroicon::OutlinedChevronUp,
            self::Urgent => Heroicon::OutlinedChevronDoubleUp,
        };
    }
}
