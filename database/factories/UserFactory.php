<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * The schema keys on `username` (see create_users_table) and requires a
     * role, so the definition must not assume the default Laravel email
     * columns. Previously unused, which is why it had drifted.
     */
    public function definition(): array
    {
        return [
            'name'     => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'role'     => 'vendor',
            'status'   => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * The `users` table has no `email` or `email_verified_at` column, so
     * Laravel's stock `unverified()` state could never be honoured and would
     * have written a column that does not exist. Account standing is expressed
     * through `status`, so the states below mirror the values the app checks.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function supervisor(): static
    {
        return $this->state(fn () => ['role' => 'supervisor']);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => 'staff']);
    }

    public function vendor(): static
    {
        return $this->state(fn () => ['role' => 'vendor']);
    }
}
