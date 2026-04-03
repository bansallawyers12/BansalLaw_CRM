<?php

namespace Database\Factories;

use App\Models\Signer;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SignerFactory extends Factory
{
    protected $model = Signer::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'token' => Str::random(64),
            'status' => 'pending',
            'signed_at' => null,
            'opened_at' => null,
            'last_reminder_sent_at' => null,
            'reminder_count' => 0,
        ];
    }

    public function signed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'signed',
            'signed_at' => now(),
            'opened_at' => now()->subMinutes(5),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'signed_at' => null,
        ]);
    }
}

