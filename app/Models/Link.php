<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToClientWork;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A web link of a client, project or task, like the contract in Drive or the staging site.
 * Files stay where they are; only the address is stored.
 *
 * @property int $id
 * @property string|null $title
 * @property string $url Always http or https.
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['client_id', 'project_id', 'task_id', 'title', 'url'])]
class Link extends Model
{
    use BelongsToClientWork;

    /** The title, or the domain when there is none. */
    public function label(): string
    {
        return filled($this->title) ? (string) $this->title : $this->host();
    }

    public function host(): string
    {
        return Str::chopStart((string) parse_url($this->url, PHP_URL_HOST), 'www.');
    }
}
