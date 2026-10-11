<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The signed-in user's UI language. Signed-out pages (login) keep APP_LOCALE. */
final class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            app()->setLocale($user->preferredLocale());
        }

        /** @var Response */
        return $next($request);
    }
}
