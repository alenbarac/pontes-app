<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MembershipPlan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MembershipPlanDiscount
{
    /**
     * Find or create the percentage-off variant of a base plan.
     * Import and the discount screen share this row (workshop + plan name).
     */
    public function findOrCreate(MembershipPlan $basePlan, int $percent): MembershipPlan
    {
        if ($basePlan->isDiscounted()) {
            throw new InvalidArgumentException('Popust se može dodati samo na osnovni plan.');
        }

        return MembershipPlan::firstOrCreate(
            [
                'workshop_id' => $basePlan->workshop_id,
                'plan' => $this->discountedName($basePlan, $percent),
            ],
            [
                'fee' => $basePlan->fee,
                'billing_frequency' => $basePlan->billing_frequency,
                'discount_type' => $percent.'% OFF',
                'total_fee' => $this->chargedAmount($basePlan, $percent),
            ]
        );
    }

    /**
     * Change the percent on an existing discount plan and recalculate the charged amount.
     */
    public function updatePercent(MembershipPlan $plan, int $percent): MembershipPlan
    {
        $base = $this->basePlan($plan);

        if (! $base) {
            throw ValidationException::withMessages([
                'percent' => 'Osnovni plan za ovaj popust više ne postoji.',
            ]);
        }

        $name = $this->discountedName($base, $percent);

        $taken = MembershipPlan::query()
            ->where('workshop_id', $plan->workshop_id)
            ->where('plan', $name)
            ->whereKeyNot($plan->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'percent' => 'Popust od '.$percent.'% već postoji za ovaj plan.',
            ]);
        }

        $plan->update([
            'plan' => $name,
            'discount_type' => $percent.'% OFF',
            'fee' => $base->fee,
            'billing_frequency' => $base->billing_frequency,
            'total_fee' => $this->chargedAmount($base, $percent),
        ]);

        return $plan->refresh();
    }

    /**
     * Delete a discount plan that no enrollment or invoice still uses.
     */
    public function delete(MembershipPlan $plan): void
    {
        if (! $plan->isDiscounted()) {
            throw ValidationException::withMessages([
                'plan' => 'Odabrani plan nije popust.',
            ]);
        }

        $inUse = $plan->memberWorkshop()->exists()
            || Invoice::query()->where('membership_plan_id', $plan->id)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'plan' => 'Popust se ne može obrisati jer ga koriste članovi ili postojeći računi.',
            ]);
        }

        $plan->delete();
    }

    public function discountedName(MembershipPlan $basePlan, int $percent): string
    {
        return sprintf('%s (%d%% OFF)', $basePlan->plan, $percent);
    }

    /**
     * Percent is applied to the base plan's catalog total_fee.
     */
    public function chargedAmount(MembershipPlan $basePlan, int $percent): float
    {
        $baseAmount = (float) ($basePlan->total_fee ?? $basePlan->fee);

        return round($baseAmount * (1 - $percent / 100), 2);
    }

    public function basePlanName(string $planName): ?string
    {
        if (preg_match('/^(.*?)\s+\(\d+(?:\.\d+)?%\s*OFF\)$/iu', $planName, $matches)) {
            $name = trim($matches[1]);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    public function basePlan(MembershipPlan $discountPlan): ?MembershipPlan
    {
        $baseName = $this->basePlanName($discountPlan->plan);

        if (! $baseName) {
            return null;
        }

        return MembershipPlan::query()
            ->where('workshop_id', $discountPlan->workshop_id)
            ->where('plan', $baseName)
            ->first();
    }

    /**
     * Base plan first, then its percentage variants from smallest to largest.
     *
     * @param  Collection<int, MembershipPlan>  $plans
     * @return Collection<int|string, Collection<int, MembershipPlan>>
     */
    public function groupForDropdown(Collection $plans): Collection
    {
        return $plans
            ->groupBy('workshop_id')
            ->map(fn (Collection $group) => $this->sortForDropdown($group)->values());
    }

    /**
     * @param  Collection<int, MembershipPlan>  $plans
     * @return Collection<int, MembershipPlan>
     */
    public function sortForDropdown(Collection $plans): Collection
    {
        return $plans->sort(function (MembershipPlan $a, MembershipPlan $b) {
            $baseA = $this->basePlanName($a->plan) ?? $a->plan;
            $baseB = $this->basePlanName($b->plan) ?? $b->plan;
            $byName = strnatcasecmp($baseA, $baseB);

            if ($byName !== 0) {
                return $byName;
            }

            $percentA = $a->isDiscounted() ? ($a->discountPercent() ?? 0) : -1;
            $percentB = $b->isDiscounted() ? ($b->discountPercent() ?? 0) : -1;

            return $percentA <=> $percentB;
        })->values();
    }
}
