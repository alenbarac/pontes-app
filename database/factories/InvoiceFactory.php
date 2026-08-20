<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $dueDate = $this->faker->dateTimeBetween('-1 year', '+6 months');

        return [
            'member_id' => Member::factory(),
            'workshop_id' => Workshop::factory(),
            'membership_plan_id' => null,
            'amount_due' => $this->faker->randomFloat(2, 10, 500),
            'amount_paid' => 0,
            'due_date' => $dueDate->format('Y-m-d'),
            'payment_status' => 'Otvoreno',
            'reference_code' => $this->faker->unique()->numerify('20251201-###-###'),
            'notes' => null,
            'slip_description' => null,
            'invoice_type' => 'membership',
        ];
    }
}
