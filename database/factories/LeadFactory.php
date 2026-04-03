<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Services\LeadFollowUpNoteService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        $leadStatus = $this->faker->randomElement(['new', 'follow_up', 'not_qualified', 'hostile']);

        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'password' => bcrypt('password'),
            'type' => 'lead', // Lead type
            'user_id' => \App\Models\Staff::query()->value('id'), // Assigned to staff (null if no staff)
            'status' => LeadFollowUpNoteService::adminsStatusForLeadStatus($leadStatus),
            'lead_status' => $leadStatus,
            'source' => $this->faker->optional()->randomElement(['website', 'referral', 'social_media', 'direct']),
            'remember_token' => Str::random(10),
            'is_archived' => 0,
            'is_deleted' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function new(): self
    {
        return $this->state(fn (array $attributes) => [
            'lead_status' => 'new',
            'status' => 1,
        ]);
    }

    public function followUp(): self
    {
        return $this->state(fn (array $attributes) => [
            'lead_status' => 'follow_up',
            'status' => 1,
        ]);
    }

    public function converted(): self
    {
        return $this->state(fn (array $attributes) => [
            'lead_status' => 'converted',
            'type' => 'client',
            'status' => 1,
        ]);
    }
}

