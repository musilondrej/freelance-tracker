<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PricingType: string implements HasColor, HasIcon, HasLabel
{
    case Hourly = 'hourly';
    case Fixed = 'fixed';
    case NonBillable = 'non_billable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => __('Hourly'),
            self::Fixed => __('Fixed price'),
            self::NonBillable => __('Non-billable'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hourly, self::Fixed, self::NonBillable => 'gray',
        };
    }

    public function chartColor(): string
    {
        return match ($this) {
            self::Hourly => '#2a78d6',
            self::Fixed => '#eb6834',
            self::NonBillable => '#898781',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Hourly => Heroicon::OutlinedClock,
            self::Fixed => Heroicon::OutlinedTag,
            self::NonBillable => Heroicon::OutlinedNoSymbol,
        };
    }
}
