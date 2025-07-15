<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MemberWorkshop;
use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $memberWorkshops = MemberWorkshop::with('membershipPlan', 'member', 'workshop')->get();

        foreach ($memberWorkshops as $mw) {
            $plan = $mw->membershipPlan;
            if (!$plan) continue;

            $startDate = $mw->membership_start_date ?? Carbon::now()->startOfMonth();
            $billingFrequency = strtolower($plan->billing_frequency);

            $invoiceCount = match ($billingFrequency) {
                'mjesečno' => 3,
                'polugodišnje' => 1,
                'godišnje' => 1,
                default => 1,
            };

            for ($i = 0; $i < $invoiceCount; $i++) {
                $dueDate = match ($billingFrequency) {
                    'mjesečno' => Carbon::parse($startDate)->copy()->addMonths($i),
                    'polugodišnje' => Carbon::parse($startDate)->copy()->addMonths(6 * $i),
                    'godišnje' => Carbon::parse($startDate)->copy()->addYears($i),
                    default => Carbon::parse($startDate),
                };

                $referenceCode = 'PONTES-' . $dueDate->format('Ym') . '-R' . $mw->workshop_id . '-C' . $mw->member_id;

                Invoice::create([
                    'member_id' => $mw->member_id,
                    'workshop_id' => $mw->workshop_id,
                    'membership_plan_id' => $plan->id,
                    'amount_due' => $plan->fee,
                    'amount_paid' => 0,
                    'due_date' => $dueDate->toDateString(),
                    'payment_status' => 'Pending',
                    'reference_code' => $referenceCode,
                    'notes' => 'Članarina za ' . $dueDate->format('m/Y'),
                ]);
            }
        }

        $this->command->info('Invoices seeded successfully!');
    }
}
