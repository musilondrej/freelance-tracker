@use('App\Support\Period')

@if ($rateOn)
    <p class="text-center text-xs text-gray-400 dark:text-gray-500">
        {{ __('Totals in several currencies are converted to CZK at the ČNB rate of :date.', ['date' => $rateOn->format(Period::DATE_FORMAT)]) }}
    </p>
@endif
