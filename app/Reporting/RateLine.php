<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Enums\Currency;
use Carbon\CarbonImmutable;

final readonly class RateLine
{
    public function __construct(
        public string $client,
        public Currency $currency,
        public int $rate,
        public int $seconds,
        public int $amount,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}
}
