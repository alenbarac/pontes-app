<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkshopDiscountRequest;
use App\Http\Requests\UpdateWorkshopDiscountRequest;
use App\Models\MembershipPlan;
use App\Models\Workshop;
use App\Services\MembershipPlanDiscount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopDiscountController extends Controller
{
    public function __construct(private MembershipPlanDiscount $discounts) {}

    public function index(Request $request): Response
    {
        $workshopId = $request->filled('workshop_id') ? $request->integer('workshop_id') : null;

        $workshops = Workshop::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $plans = MembershipPlan::query()
            ->with('workshop:id,name')
            ->withCount('memberWorkshop as members_count')
            ->orderBy('plan')
            ->get();

        $basesByKey = $plans
            ->reject(fn (MembershipPlan $plan) => $plan->isDiscounted())
            ->keyBy(fn (MembershipPlan $plan) => $plan->workshop_id.'|'.$plan->plan);

        $basePlans = $plans
            ->reject(fn (MembershipPlan $plan) => $plan->isDiscounted())
            ->groupBy('workshop_id')
            ->map(fn ($group) => $group->map(fn (MembershipPlan $plan) => [
                'id' => $plan->id,
                'plan' => $plan->plan,
                'total_fee' => (float) ($plan->total_fee ?? $plan->fee),
                'billing_frequency' => $plan->billing_frequency,
            ])->values());

        $discounts = $plans
            ->filter(fn (MembershipPlan $plan) => $plan->isDiscounted())
            ->when($workshopId, fn ($group) => $group->where('workshop_id', $workshopId))
            ->map(function (MembershipPlan $plan) use ($basesByKey) {
                $baseName = $this->discounts->basePlanName($plan->plan);
                $base = $baseName ? $basesByKey->get($plan->workshop_id.'|'.$baseName) : null;
                $baseAmount = $base
                    ? (float) ($base->total_fee ?? $base->fee)
                    : $plan->amountBeforeDiscount();

                return [
                    'id' => $plan->id,
                    'workshop_id' => $plan->workshop_id,
                    'workshop_name' => $plan->workshop?->name,
                    'plan' => $plan->plan,
                    'base_plan' => $baseName ?? $plan->plan,
                    'percent' => $plan->discountPercent(),
                    'base_amount' => $baseAmount,
                    'total_fee' => (float) ($plan->total_fee ?? $plan->fee),
                    'billing_frequency' => $plan->billing_frequency,
                    'members_count' => (int) $plan->members_count,
                ];
            })
            ->sort(function (array $a, array $b) {
                $byWorkshop = strnatcasecmp((string) $a['workshop_name'], (string) $b['workshop_name']);
                if ($byWorkshop !== 0) {
                    return $byWorkshop;
                }

                $byPlan = strnatcasecmp((string) $a['base_plan'], (string) $b['base_plan']);
                if ($byPlan !== 0) {
                    return $byPlan;
                }

                return ($a['percent'] ?? 0) <=> ($b['percent'] ?? 0);
            })
            ->values();

        return Inertia::render('Workshops/Discounts', [
            'workshops' => $workshops,
            'basePlans' => $basePlans,
            'discounts' => $discounts,
            'workshopId' => $workshopId ? (string) $workshopId : '',
        ]);
    }

    public function store(StoreWorkshopDiscountRequest $request): RedirectResponse
    {
        $base = MembershipPlan::query()->findOrFail($request->integer('membership_plan_id'));
        $percent = $request->integer('percent');
        $plan = $this->discounts->findOrCreate($base, $percent);

        if (! $plan->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'percent' => 'Popust od '.$percent.'% već postoji za ovaj plan.',
            ]);
        }

        return back()->with('success', 'Popust je dodan.');
    }

    public function update(UpdateWorkshopDiscountRequest $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        if (! $membershipPlan->isDiscounted()) {
            throw ValidationException::withMessages([
                'percent' => 'Odabrani plan nije popust.',
            ]);
        }

        $this->discounts->updatePercent($membershipPlan, $request->integer('percent'));

        return back()->with('success', 'Popust je ažuriran.');
    }

    public function destroy(MembershipPlan $membershipPlan): RedirectResponse
    {
        $this->discounts->delete($membershipPlan);

        return back()->with('success', 'Popust je obrisan.');
    }
}
