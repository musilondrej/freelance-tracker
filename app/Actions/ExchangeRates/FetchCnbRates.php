<?php

declare(strict_types=1);

namespace App\Actions\ExchangeRates;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stores today's ČNB fixing for the app's currencies. Idempotent: the file is dated by ČNB,
 * so on weekends it returns the last business day and nothing new is stored.
 */
final class FetchCnbRates
{
    public const string URL = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';

    /** @return int number of stored rates */
    public function handle(): int
    {
        $lines = preg_split('/\R/', trim(Http::retry(3, 1000)->timeout(10)->get(self::URL)->throw()->body())) ?: [];

        // "09.10.2026 #195", then "země|měna|množství|kód|kurz", then one line per currency.
        if (preg_match('/^(\d{2}\.\d{2}\.\d{4}) #\d+$/', $lines[0] ?? '', $date) !== 1) {
            throw new RuntimeException('Unexpected ČNB rate file.');
        }

        $rateOn = CarbonImmutable::createFromFormat('!d.m.Y', $date[1]);

        if (! $rateOn instanceof CarbonImmutable) {
            throw new RuntimeException('Unexpected ČNB rate date.');
        }

        $rows = [];

        foreach (array_slice($lines, 2) as $line) {
            [, , $amount, $code, $rate] = array_pad(explode('|', $line), 5, '');
            $currency = Currency::tryFrom($code);

            // Only the app's currencies; "24,365" is stored as thousandths, 24365, without floats.
            if ($currency !== null && $currency !== Currency::CZK && preg_match('/^(\d+),(\d{3})$/', $rate, $parts) === 1) {
                $rows[] = ['rate_on' => $rateOn->toDateString(), 'currency' => $currency->value, 'amount' => (int) $amount, 'rate' => (int) ($parts[1].$parts[2])];
            }
        }

        // One statement, idempotent: the same fixing fetched twice updates in place.
        ExchangeRate::query()->upsert($rows, ['currency', 'rate_on'], ['amount', 'rate']);

        return count($rows);
    }
}
