<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientEoiReference>
 */
class ClientEoiReferenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \App\Models\ClientEoiReference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => \App\Models\Admin::factory(),
            'admin_id' => 1,
            'EOI_number' => 'EOI' . fake()->unique()->numerify('########'),
            'EOI_subclass' => fake()->randomElement(['189', '190', '491']),
            'eoi_subclasses' => [fake()->randomElement(['189', '190', '491'])],
            'EOI_state' => fake()->randomElement(['VIC', 'NSW', 'QLD', 'SA', 'WA']),
            'eoi_states' => [fake()->randomElement(['VIC', 'NSW', 'QLD', 'SA', 'WA'])],
            'EOI_occupation' => '261313',
            'EOI_point' => fake()->numberBetween(65, 95),
            'EOI_submission_date' => now()->subDays(rand(1, 365)),
            'eoi_status' => fake()->randomElement(['draft', 'submitted', 'invited']),
        ];
    }
}
