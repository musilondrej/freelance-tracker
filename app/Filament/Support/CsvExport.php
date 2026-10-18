<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Money;
use Illuminate\Support\Str;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV for spreadsheets with Czech locale: UTF-8 with BOM, ";" separator, decimal comma, no thousands separator.
 */
final class CsvExport
{
    /**
     * @param  list<string>  $header
     * @param  iterable<array<int, string|int|null>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = new SplFileObject('php://output', 'wb');
            $out->fwrite("\xEF\xBB\xBF");
            $out->fputcsv($header, ';', '"', '');

            foreach ($rows as $row) {
                $out->fputcsv(array_map(self::cell(...), $row), ';', '"', '');
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV injection (OWASP): text starting with = + - @ tab or CR runs as a formula in Excel, so it gets a leading '.
     * Plain numbers such as "-1200,50" stay numbers.
     */
    private static function cell(string|int|null $value): string|int|null
    {
        if (! is_string($value) || ! Str::startsWith($value, ['=', '+', '-', '@', "\t", "\r"]) || Str::isMatch('/^-?\d+(,\d+)?$/', $value)) {
            return $value;
        }

        return "'".$value;
    }

    /** Formats 120050 as "1200,50". */
    public static function amount(?int $minor): string
    {
        return $minor === null ? '' : Money::toDecimal($minor);
    }

    /** Formats 5400 seconds as "1,50" hours, rounded half up to 2 places. */
    public static function hours(?int $seconds): string
    {
        return $seconds === null ? '' : self::amount(Money::roundHalfUpDiv($seconds * 100, 3600));
    }
}
