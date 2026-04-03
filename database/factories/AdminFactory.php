<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \App\Models\Admin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'type' => 'client',
            'country' => 'Australia',
            'state' => 'VIC',
            'city' => fake()->city(),
            'address' => fake()->address(),
            'zip' => fake()->postcode(),
            'status' => 1,
            'dob' => fake()->date('Y-m-d', '-25 years'),
        ];
    }
}
