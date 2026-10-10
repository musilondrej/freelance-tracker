<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Paused => __('Paused'),
            self::Completed => __('Completed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active, self::Completed => 'gray',
            self::Paused => 'warning',
        };
    }
}
