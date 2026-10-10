<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'app_authentication_secret' => (new Google2FA)->generateSecretKey(),
        ];
    }

    /**
     * A user without multi-factor authentication yet, like right after `make:filament-user`.
     */
    public function withoutMultiFactorAuthentication(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_authentication_secret' => null,
        ]);
    }
}
