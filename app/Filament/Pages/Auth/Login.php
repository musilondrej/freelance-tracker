<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/** Plain sign-in form without the brand logo. */
class Login extends BaseLogin
{
    public function hasLogo(): bool
    {
        return false;
    }
}
