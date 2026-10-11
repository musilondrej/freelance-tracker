<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\Locale;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?string $app_authentication_secret
 * @property ?list<string> $app_authentication_recovery_codes
 * @property int|null $timer_alert_seconds Alert when a timer runs longer than this. Null means off.
 * @property int|null $recurring_reminder_days Remind this many days before a recurring period starts. Null means off.
 * @property int|null $deadline_reminder_days Warn this many days before a task's deadline. Null means off.
 * @property bool $notify_by_mail Also send alerts by e-mail. In-app notifications are always on.
 * @property string|null $billing_name Supplier name on statements. Null = no supplier block.
 * @property string|null $billing_address
 * @property string|null $billing_registration_number
 * @property string|null $billing_vat_number
 * @property string|null $billing_bank_account
 * @property int $workday_hours A full working day: the chart line and the day/week targets.
 * @property Currency $default_currency For new clients.
 * @property int|null $default_hourly_rate For new clients, in minor units.
 * @property Locale $locale UI language, also used for e-mail alerts.
 */
#[Fillable([
    'name', 'email', 'password', 'timer_alert_seconds', 'recurring_reminder_days', 'deadline_reminder_days', 'notify_by_mail',
    'billing_name', 'billing_address', 'billing_registration_number', 'billing_vat_number', 'billing_bank_account',
    'workday_hours', 'default_currency', 'default_hourly_rate', 'locale',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference
{
    /** Same as the column default, for contexts without a signed-in user. */
    public const int DEFAULT_WORKDAY_HOURS = 8;

    public const int WORKDAYS_PER_WEEK = 5;

    protected $attributes = [
        'billing_name' => null,
        'billing_address' => null,
        'billing_registration_number' => null,
        'billing_vat_number' => null,
        'billing_bank_account' => null,
        'deadline_reminder_days' => 2,
        'workday_hours' => self::DEFAULT_WORKDAY_HOURS,
        'default_currency' => Currency::CZK->value,
        'default_hourly_rate' => null,
        'locale' => Locale::Czech->value,
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use Notifiable;

    /** @return list<string> */
    public function notificationChannels(): array
    {
        return $this->notify_by_mail ? ['database', 'mail'] : ['database'];
    }

    /** The signed-in user's working day, or the default without one (console, tests). */
    public static function workdayHours(): int
    {
        $user = auth()->user();

        return $user instanceof self ? $user->workday_hours : self::DEFAULT_WORKDAY_HOURS;
    }

    public function preferredLocale(): string
    {
        return $this->locale->value;
    }

    // Single-user app: every account is the owner.
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'timer_alert_seconds' => 'integer',
            'recurring_reminder_days' => 'integer',
            'deadline_reminder_days' => 'integer',
            'notify_by_mail' => 'boolean',
            'workday_hours' => 'integer',
            'default_currency' => Currency::class,
            'default_hourly_rate' => 'integer',
            'locale' => Locale::class,
        ];
    }
}
