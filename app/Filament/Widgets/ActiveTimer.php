<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ActiveTimer extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.active-timer';

    protected int|string|array $columnSpan = 'full';
}
