<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToClientWork;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A dated entry in a client's history.
 *
 * @property int $id
 * @property CarbonImmutable $noted_on
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['client_id', 'project_id', 'task_id', 'noted_on', 'body'])]
class Note extends Model
{
    use BelongsToClientWork;

    protected function casts(): array
    {
        return [
            'noted_on' => 'immutable_date',
        ];
    }
}
