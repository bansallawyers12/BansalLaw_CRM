<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailAccount>
 */
class EmailAccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailAccount::class;

    /**
     * The database connection that should be used by the factory.
     */
    protected $connection = 'second_db';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'zoho',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => $this->faker->optional(0.3)->password(),
            'access_token' => $this->faker->optional(0.8)->sha256(),
            'refresh_token' => $this->faker->optional(0.7)->sha256(),
            'last_connection_error' => $this->faker->optional(0.2)->sentence(),
            'last_connection_attempt' => $this->faker->optional(0.6)->dateTimeBetween('-1 week', 'now'),
            'connection_status' => $this->faker->boolean(80), // 80% chance of true
        ];
    }

    /**
     * Indicate that the account is connected successfully.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_status' => true,
            'last_connection_error' => null,
            'last_connection_attempt' => now(),
        ]);
    }

    /**
     * Indicate that the account has connection issues.
     */
    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_status' => false,
            'last_connection_error' => $this->faker->randomElement([
                'Authentication failed',
                'Connection timeout',
                'Invalid credentials',
                'Server unavailable'
            ]),
            'last_connection_attempt' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }
}
