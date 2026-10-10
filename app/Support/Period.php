<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * A time range [start, end). Day boundaries are in Europe/Prague, values are in UTC
 * for timestamptz columns. A record belongs to the period by its start time, which is its local date.
 */
final readonly class Period
{
    public const string DATE_FORMAT = 'j. n. Y';

    public const string DATETIME_FORMAT = 'j. n. Y H:i';

    /** Day and month without the year, for charts and cards. */
    public const string SHORT_DATE_FORMAT = 'j. n.';

    private function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function timezone(): string
    {
        return Config::string('app.display_timezone');
    }

    /**
     * Today's local date at midnight, like the `immutable_date` casts, so it compares with DATE columns.
     * Use it instead of `today()`, which is in UTC.
     */
    public static function localToday(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(self::timezone())->toDateString());
    }

    public static function today(): self
    {
        $now = CarbonImmutable::now(self::timezone());

        return self::local($now->startOfDay(), $now->startOfDay()->addDay());
    }

    public static function thisWeek(): self
    {
        $now = CarbonImmutable::now(self::timezone());

        return self::local($now->startOfWeek(), $now->startOfWeek()->addWeek());
    }

    public static function thisMonth(): self
    {
        $now = CarbonImmutable::now(self::timezone());

        return self::local($now->startOfMonth(), $now->startOfMonth()->addMonth());
    }

    /** One local day, like day('2026-10-11'): from its midnight to the next. */
    public static function day(string $date): self
    {
        return self::between($date, $date);
    }

    /** Everything, for all-time totals such as project budgets. */
    public static function allTime(): self
    {
        return new self(new CarbonImmutable('1970-01-01', 'UTC'), new CarbonImmutable('9999-01-01', 'UTC'));
    }

    /** Inclusive local dates, like between('2026-09-01', '2026-09-30'). */
    public static function between(string $fromDate, string $toDate): self
    {
        $from = CarbonImmutable::parse($fromDate, self::timezone())->startOfDay();
        $to = CarbonImmutable::parse($toDate, self::timezone())->startOfDay()->addDay();

        return self::local($from, $to);
    }

    /** First local date of the period for DATE columns, like "2026-09-01". */
    public function fromDate(): string
    {
        return $this->start->setTimezone(self::timezone())->toDateString();
    }

    /** Last local date of the period, inclusive. */
    public function toDate(): string
    {
        return $this->end->setTimezone(self::timezone())->subDay()->toDateString();
    }

    private static function local(CarbonImmutable $start, CarbonImmutable $end): self
    {
        return new self($start->utc(), $end->utc());
    }
}
