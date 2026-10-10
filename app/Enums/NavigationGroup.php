<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup implements HasLabel
{
    case Work;
    case Engagements;
    case Finance;

    public function getLabel(): string
    {
        return match ($this) {
            self::Work => __('Work'),
            self::Engagements => __('Engagements'),
            self::Finance => __('Finance'),
        };
    }
}
