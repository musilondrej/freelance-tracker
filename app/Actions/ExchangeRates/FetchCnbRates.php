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
        $lines = $this->download();
        $rateOn = $this->parseDate($lines[0] ?? '');
        $currencyLines = array_slice($lines, 2);

        $rows = [];

        foreach ($currencyLines as $line) {
            $rate = $this->parseCurrencyLine($line);

            if ($rate !== null) {
                $rows[] = ['rate_on' => $rateOn->toDateString(), ...$rate];
            }
        }

        ExchangeRate::query()->upsert($rows, ['currency', 'rate_on'], ['amount', 'rate']);

        return count($rows);
    }

    /**
     * A date line, a column header, then one line per currency.
     *
     * @return list<string>
     */
    private function download(): array
    {
        $body = Http::retry(3, 1000)->timeout(10)->get(self::URL)->throw()->body();

        return preg_split('/\R/', trim($body)) ?: [];
    }

    /** "09.10.2026 #195" */
    private function parseDate(string $line): CarbonImmutable
    {
        if (preg_match('/^(\d{2}\.\d{2}\.\d{4}) #\d+$/', $line, $match) !== 1) {
            throw new RuntimeException('Unexpected ČNB rate file.');
        }

        $date = CarbonImmutable::createFromFormat('!d.m.Y', $match[1]);

        if (! $date instanceof CarbonImmutable) {
            throw new RuntimeException('Unexpected ČNB rate date.');
        }

        return $date;
    }

    /**
     * "EMU|euro|1|EUR|24,365" becomes rate 24365 in thousandths, without floats.
     * Null for currencies the app does not use.
     *
     * @return array{currency: string, amount: int, rate: int}|null
     */
    private function parseCurrencyLine(string $line): ?array
    {
        [, , $amount, $code, $rate] = array_pad(explode('|', $line), 5, '');
        $currency = Currency::tryFrom($code);

        if ($currency === null || $currency === Currency::CZK) {
            return null;
        }

        if (preg_match('/^(\d+),(\d{3})$/', $rate, $parts) !== 1) {
            return null;
        }

        return ['currency' => $currency->value, 'amount' => (int) $amount, 'rate' => (int) ($parts[1].$parts[2])];
    }
}
