<?php

namespace App\Http\Controllers;

use App\Http\Resources\MemberGroupResource;
use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Models\MemberGroup;
use App\Services\DashboardFinanceService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardFinanceService $dashboardFinanceService,
    ) {}

    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        $groups = MemberGroup::with('assignedWorkshop')
            ->withCount('members')
            ->get()
            ->map(function ($group) {
                return new MemberGroupResource($group);
            });

        return Inertia::render('Dashboard', [
            'revenue' => $this->dashboardFinanceService->buildMetrics(),
            'groups' => $groups,
            'mailingCard' => $this->mailingCard(),
        ]);
    }

    /**
     * @return array{active: list<array<string, mixed>>, latest: array<string, mixed>|null}
     */
    private function mailingCard(): array
    {
        $active = Mailing::query()
            ->with('memberGroup:id,name')
            ->whereHas('invoiceMailings', fn ($query) => $query->where('status', InvoiceMailing::STATUS_QUEUED))
            ->latest('id')
            ->get();

        $latest = Mailing::query()
            ->with('memberGroup:id,name')
            ->latest('id')
            ->first();

        $counts = Mailing::countsFor(
            $active->when($latest !== null, fn ($rows) => $rows->push($latest))->unique('id')
        );

        $present = function (Mailing $mailing) use ($counts) {
            return [
                ...$mailing->summary($counts[$mailing->id] ?? null),
                'url' => route('mailings.show', $mailing),
            ];
        };

        return [
            'active' => $active
                ->filter(fn (Mailing $mailing) => ($counts[$mailing->id]['queued'] ?? 0) > 0)
                ->map($present)
                ->values()
                ->all(),
            'latest' => $latest ? $present($latest) : null,
        ];
    }
}
