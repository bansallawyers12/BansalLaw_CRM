<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        // Ensure common roles exist (staff.role is FK to user_roles)
        UserRole::firstOrCreate(['id' => 1], ['name' => 'Super Admin', 'description' => 'Super administrator']);
        UserRole::firstOrCreate(['id' => 2], ['name' => 'Staff', 'description' => 'Regular staff']);

        return [
            'first_name' => fake()->firstName(),
            'last_name'  => fake()->lastName(),
            'email'      => fake()->unique()->safeEmail(),
            'password'   => Hash::make('password'),
            'role'       => 2,
            'status'     => 1,
            'position'   => fake()->jobTitle(),
            'phone'      => fake()->phoneNumber(),
        ];
    }

    /** Staff with Admin Console access (role 1 = super admin) */
    public function superAdmin(): self
    {
        return $this->state(fn (array $attributes) => [
            'role' => 1,
        ]);
    }

    /** Regular staff (role 2) */
    public function regularStaff(): self
    {
        return $this->state(fn (array $attributes) => [
            'role' => 2,
        ]);
    }
}
