<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'file_name' => $this->faker->word() . '.pdf',
            'filetype' => 'application/pdf',
            'myfile' => 'documents/' . $this->faker->uuid() . '.pdf',
            'file_size' => $this->faker->numberBetween(1000, 5000000),
            'status' => $this->faker->randomElement(['draft', 'sent', 'signed']),
            'created_by' => Admin::factory(),
        ];
    }

    public function draft(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function sent(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
        ]);
    }

    public function signed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'signed',
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }
}

