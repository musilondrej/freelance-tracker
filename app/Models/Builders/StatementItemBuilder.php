<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Enums\PricingType;
use App\Enums\StatementStatus;
use App\Models\StatementItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of StatementItem
 *
 * @extends Builder<TModel>
 */
final class StatementItemBuilder extends Builder
{
    public function fixed(): static
    {
        return $this->where('statement_items.pricing_type', PricingType::Fixed);
    }

    /** On a draft or sent statement. A cancelled statement keeps its items only as history. */
    public function onActiveStatement(): static
    {
        return $this->whereRelation('statement', 'status', '!=', StatementStatus::Cancelled);
    }
}
