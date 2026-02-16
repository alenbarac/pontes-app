<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 * 
 * Note: This factory is kept for reference but is not used in production.
 * Members are imported via CSV import functionality.
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
        // Factory disabled - use CSV import for members instead
        // This factory is kept for reference purposes only
        return [
            'first_name' => 'Member',
            'last_name' => 'Example',
            'date_of_birth' => '2000-01-01',
            'phone_number' => '000-000-0000',
            'email' => 'member@example.com',
            'is_active' => true,
            'parent_contact' => null,
            'parent_email' => null,
            'invoice_email' => null,
        ];
    }
}
