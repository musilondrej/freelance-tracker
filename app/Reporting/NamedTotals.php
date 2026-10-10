<?php

declare(strict_types=1);

namespace App\Reporting;

final readonly class NamedTotals
{
    public function __construct(
        public string $name,
        public CurrencyTotals $totals,
    ) {}
}
