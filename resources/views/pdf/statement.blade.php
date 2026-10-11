@use('App\Enums\PricingType')
@use('App\Support\Duration')
@use('App\Support\Money')
@use('App\Support\Period')

@php
    $client = $statement->client;
    $entries = $statement->items->flatMap->timeEntries;
    $money = fn (int $amount): string => Money::format($amount, $statement->currency, precision: 2);
    $date = fn ($at): string => $at->setTimezone(Period::timezone())->format(Period::DATE_FORMAT);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Statement #:id', ['id' => $statement->id]) }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 4pt; }
        .muted { color: #666; }
        .meta { margin-bottom: 18pt; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8.5pt; color: #666; border-bottom: 1px solid #ccc; padding: 4pt; }
        td { padding: 4pt; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .item td { border-top: 1px solid #eee; font-weight: bold; }
        .entry td { font-size: 8.5pt; color: #444; padding-top: 1pt; padding-bottom: 1pt; }
        .total td { border-top: 2px solid #111; font-size: 11pt; font-weight: bold; padding-top: 6pt; }
        .note { margin-top: 18pt; white-space: pre-line; }
        .parties { margin-bottom: 8pt; }
        .parties td { width: 50%; padding: 0 12pt 0 0; }
        .address { white-space: pre-line; }
    </style>
</head>
<body>
    <h1>{{ __('Statement of work') }} #{{ $statement->id }}</h1>
    <table class="parties">
        <tr>
            @if (filled($supplier?->billing_name))
                <td>
                    <span class="muted">{{ __('Supplier') }}</span><br>
                    <strong>{{ $supplier->billing_name }}</strong><br>
                    @if (filled($supplier->billing_address))
                        <span class="address">{{ $supplier->billing_address }}</span><br>
                    @endif
                    @if (filled($supplier->billing_registration_number))
                        {{ __('Company ID (IČO)') }}: {{ $supplier->billing_registration_number }}<br>
                    @endif
                    @if (filled($supplier->billing_vat_number))
                        {{ __('VAT ID (DIČ)') }}: {{ $supplier->billing_vat_number }}<br>
                    @endif
                    @if (filled($supplier->billing_bank_account))
                        {{ __('Bank account') }}: {{ $supplier->billing_bank_account }}<br>
                    @endif
                </td>
            @endif
            <td>
                @if (filled($supplier?->billing_name))
                    <span class="muted">{{ __('Customer') }}</span><br>
                @endif
                <strong>{{ $client->company_name ?? $client->name }}</strong><br>
                @if ($client->address() !== '')
                    {{ $client->address() }}<br>
                @endif
                @if ($client->registration_number)
                    {{ __('Company ID (IČO)') }}: {{ $client->registration_number }}<br>
                @endif
            </td>
        </tr>
    </table>
    <div class="meta">
        <span class="muted">
            @if ($entries->isNotEmpty())
                {{ __('Period') }}: {{ $date($entries->min('started_at')) }} – {{ $date($entries->max('started_at')) }} ·
            @endif
            {{ __('Date') }}: {{ ($statement->sent_on ?? Period::localToday())->format(Period::DATE_FORMAT) }}
        </span>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="num">{{ __('Time') }}</th>
                <th class="num">{{ __('Rate') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($statement->items as $item)
                <tr class="item">
                    <td>{{ $item->task->key() }} · {{ $item->description }}</td>
                    <td class="num">{{ $item->billed_seconds > 0 ? Duration::format($item->billed_seconds).' h' : '' }}</td>
                    <td class="num">{{ $item->pricing_type === PricingType::Hourly ? $money((int) $item->unit_price).'/h' : __('Fixed price') }}</td>
                    <td class="num">{{ $money($item->amount) }}</td>
                </tr>
                @foreach ($item->timeEntries->sortBy('started_at') as $entry)
                    <tr class="entry">
                        <td>{{ $date($entry->started_at) }}{{ $entry->note ? ' · '.$entry->note : '' }}</td>
                        <td class="num">{{ Duration::format($entry->duration_seconds) }} h</td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="total">
                <td colspan="3">{{ __('Total (excl. VAT)') }}</td>
                <td class="num">{{ $money($statement->total()) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($statement->note)
        <div class="note">{{ $statement->note }}</div>
    @endif
</body>
</html>
