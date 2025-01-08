<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'birth_year' => $this->faker->numberBetween(2000, 2015),
            'phone_number' => $this->faker->phoneNumber,
            'email' => $this->faker->unique()->safeEmail,
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'parent_contact' => $this->faker->optional()->phoneNumber,
            'parent_email' => $this->faker->optional()->safeEmail,
        ];
    }
}
