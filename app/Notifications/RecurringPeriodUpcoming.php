<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\BillingTiming;
use App\Enums\PricingType;
use App\Filament\Resources\RecurringTasks\RecurringTaskResource;
use App\Models\RecurringTask;
use App\Models\User;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Queued: a slow mail server never holds up the scheduler. */
final class RecurringPeriodUpcoming extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RecurringTask $recurring,
        private readonly CarbonImmutable $periodStart,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->notificationChannels();
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->info()
            ->icon(Heroicon::OutlinedArrowPath)
            ->title($this->title())
            ->body($this->body())
            ->actions([Action::make('open')->label(__('Open'))->url($this->url())])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello!'))
            ->line($this->body())
            ->action(__('Open'), $this->url());
    }

    private function title(): string
    {
        return __('":title" renews on :date', [
            'title' => $this->recurring->title,
            'date' => $this->periodStart->format(Period::DATE_FORMAT),
        ]);
    }

    private function body(): string
    {
        $client = $this->recurring->client;
        $body = __('Client: :client.', ['client' => $client->name]);

        if ($this->recurring->pricing_type === PricingType::Fixed && $this->recurring->fixed_price !== null) {
            $body .= ' '.__(':amount, billable :timing.', [
                'amount' => Money::format($this->recurring->fixed_price, $client->currency),
                'timing' => $this->recurring->billing_timing === BillingTiming::InAdvance ? __('at the period start') : __('after the period'),
            ]);
        }

        return $body;
    }

    private function url(): string
    {
        return RecurringTaskResource::getUrl('edit', ['record' => $this->recurring]);
    }
}
