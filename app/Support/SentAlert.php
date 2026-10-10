<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Sends each alert only once. The unique key in `sent_alerts` is claimed atomically. */
final class SentAlert
{
    /** False when the alert with this key was already sent. */
    public static function claim(string $key): bool
    {
        return DB::table('sent_alerts')->insertOrIgnore(['key' => $key, 'sent_at' => now()]) === 1;
    }
}
