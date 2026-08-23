<?php

namespace App\Http\Controllers;

use App\Http\Resources\MemberGroupResource;
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
        ]);
    }
}
