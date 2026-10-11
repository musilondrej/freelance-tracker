<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Enums\Currency;
use App\Enums\Locale;
use App\Filament\Forms\DurationInput;
use App\Filament\Forms\MoneyInput;
use App\Filament\Forms\WorkFields;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditProfile extends BaseEditProfile
{
    public function getMaxContentWidth(): Width
    {
        return Width::FiveExtraLarge;
    }

    protected function getFormActions(): array
    {
        return [$this->getSaveFormAction()];
    }

    public function getFormActionsAlignment(): Alignment
    {
        return Alignment::End;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Account'))
                ->description(__('Name, e-mail and password for signing in.'))
                ->aside()
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
            Section::make(__('Billing details'))
                ->description(__('You as the supplier on statement PDFs. Empty name = not printed.'))
                ->aside()
                ->columns(2)
                ->schema([
                    TextInput::make('billing_name')->label(__('Name or company'))->maxLength(255)->columnSpanFull(),
                    Textarea::make('billing_address')->label(__('Address'))->rows(3)->columnSpanFull(),
                    TextInput::make('billing_registration_number')->label(__('Company ID (IČO)'))->maxLength(255),
                    TextInput::make('billing_vat_number')->label(__('VAT ID (DIČ)'))->maxLength(255),
                    TextInput::make('billing_bank_account')->label(__('Bank account'))->maxLength(255)->columnSpanFull(),
                ]),
            Section::make(__('Preferences'))
                ->description(__('Language, defaults for new clients and the length of your working day.'))
                ->aside()
                ->columns(2)
                ->schema([
                    Select::make('locale')->label(__('Language'))->options(Locale::class)->required()->columnSpanFull(),
                    Select::make('default_currency')->label(__('Default currency'))->options(Currency::class)->required(),
                    MoneyInput::make('default_hourly_rate', positive: true)
                        ->label(__('Default hourly rate'))
                        ->suffix(fn (Get $get): string => WorkFields::rateSuffix($get('default_currency'))),
                    TextInput::make('workday_hours')
                        ->label(__('Working day'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(24)
                        ->required()
                        ->suffix('h'),
                ]),
            Section::make(__('Alerts'))
                ->description(__('Alerts always appear under the bell; e-mail is optional.'))
                ->aside()
                ->schema([
                    DurationInput::make('timer_alert_seconds')
                        ->label(__('Alert when a timer runs longer than'))
                        ->placeholder(__('Off'))
                        ->helperText(__('E.g. 4h.')),
                    TextInput::make('recurring_reminder_days')
                        ->label(__('Remind of recurring periods'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(90)
                        ->placeholder(__('Off'))
                        ->suffix(__('days ahead')),
                    TextInput::make('deadline_reminder_days')
                        ->label(__('Warn of task deadlines'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(30)
                        ->placeholder(__('Off'))
                        ->suffix(__('days ahead')),
                    Toggle::make('notify_by_mail')->label(__('Also send alerts by e-mail')),
                ]),
        ]);
    }

    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        if (! Filament::hasMultiFactorAuthentication()) {
            return null;
        }

        $user = $this->getUser();

        return Section::make(__('Two-factor authentication'))
            ->aside()
            ->schema(collect(Filament::getMultiFactorAuthenticationProviders())
                ->sort(fn (MultiFactorAuthenticationProvider $provider): int => $provider->isEnabled($user) ? 0 : 1)
                ->map(fn (MultiFactorAuthenticationProvider $provider): Component => Group::make($provider->getManagementSchemaComponents())
                    ->statePath($provider->getId()))
                ->all());
    }
}
