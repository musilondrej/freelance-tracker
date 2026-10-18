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
    /** Long-running work, like development for a regular client. Open and trackable, but kept off the kanban. */
    case Ongoing = 'ongoing';
    case Done = 'done';

    /**
     * The kanban columns.
     *
     * @return list<self>
     */
    public static function onBoard(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status !== self::Ongoing));
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Backlog => __('Backlog'),
            self::Todo => __('To do'),
            self::InProgress => __('In progress'),
            self::Waiting => __('Waiting'),
            self::Review => __('Review'),
            self::Ongoing => __('Ongoing'),
            self::Done => __('Done'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Backlog, self::Todo, self::Review => 'gray',
            self::InProgress => 'primary',
            self::Waiting => 'warning',
            self::Ongoing => 'info',
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
            self::Ongoing => Heroicon::OutlinedArrowPath,
            self::Done => Heroicon::OutlinedCheckCircle,
        };
    }
}
