<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\On;

/** Re-renders the component when the global timer starts, stops or logs time. */
trait RefreshesOnTimerChange
{
    #[On('timer-changed')]
    public function refreshOnTimerChange(): void {}
}
