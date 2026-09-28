<?php

namespace Database\Factories;

use App\Models\Mailing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mailing>
 */
class MailingFactory extends Factory
{
    protected $model = Mailing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => Mailing::TYPE_SLIP,
            'label' => 'Uplatnice 09/2026',
            'source' => Mailing::SOURCE_INVOICES,
            'member_group_id' => null,
            'month' => '2026-09',
            'user_id' => User::factory(),
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
