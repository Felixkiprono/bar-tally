<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'customer',
            'tenant_id' => 1,
            'telephone' => fake()->phoneNumber(),
            'location' => fake()->address(),
            'balance' => 0,
            'overpayment' => 0,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a customer with outstanding balance
     */
    public function withBalance(float $balance = null): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance ?? fake()->randomFloat(2, 100, 5000),
        ]);
    }

    /**
     * Create a customer with overpayment
     */
    public function withOverpayment(float $overpayment = null): static
    {
        return $this->state(fn (array $attributes) => [
            'overpayment' => $overpayment ?? fake()->randomFloat(2, 100, 1000),
        ]);
    }

    /**
     * Create an admin user
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
