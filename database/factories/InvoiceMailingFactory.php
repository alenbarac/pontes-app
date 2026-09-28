<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceMailing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceMailing>
 */
class InvoiceMailingFactory extends Factory
{
    protected $model = InvoiceMailing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'member_id' => function (array $attributes) {
                return Invoice::query()->findOrFail($attributes['invoice_id'])->member_id;
            },
            'type' => InvoiceMailing::TYPE_SLIP,
            'recipient' => $this->faker->safeEmail(),
            'status' => InvoiceMailing::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceMailing::STATUS_QUEUED,
            'error' => null,
            'sent_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceMailing::STATUS_FAILED,
            'error' => InvoiceMailing::FAILURE_MESSAGE,
            'sent_at' => null,
        ]);
    }
}
