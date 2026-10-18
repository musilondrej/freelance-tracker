<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Review = 'review';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Backlog => __('Backlog'),
            self::Todo => __('To do'),
            self::InProgress => __('In progress'),
            self::Waiting => __('Waiting'),
            self::Review => __('Review'),
            self::Done => __('Done'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Backlog, self::Todo, self::Review => 'gray',
            self::InProgress => 'primary',
            self::Waiting => 'warning',
            self::Done => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Backlog => Heroicon::OutlinedInboxStack,
            self::Todo => Heroicon::OutlinedArrowRightCircle,
            self::InProgress => Heroicon::OutlinedPlayCircle,
            self::Waiting => Heroicon::OutlinedPauseCircle,
            self::Review => Heroicon::OutlinedEye,
            self::Done => Heroicon::OutlinedCheckCircle,
        };
    }
}
