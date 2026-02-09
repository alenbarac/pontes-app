<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Invoice;
use App\Models\MemberGroup;
use App\Models\Workshop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        // Get statistics
        $stats = [
            'total_members' => Member::count(),
            'opened_invoices' => Invoice::where('payment_status', 'Otvoreno')->count(),
            'total_invoices' => Invoice::count(),
            'total_groups' => MemberGroup::count(),
            'total_workshops' => Workshop::count(),
        ];

        // Get recent invoices (last 5)
        $recentInvoices = Invoice::with(['member', 'workshop'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($invoice) {
                $dueDate = $invoice->due_date;
                if ($dueDate && !($dueDate instanceof Carbon)) {
                    $dueDate = Carbon::parse($dueDate);
                }
                
                return [
                    'id' => $invoice->id,
                    'reference_code' => $invoice->reference_code,
                    'member_name' => $invoice->member ? $invoice->member->first_name . ' ' . $invoice->member->last_name : 'N/A',
                    'workshop_name' => $invoice->workshop ? $invoice->workshop->name : 'N/A',
                    'amount_due' => number_format($invoice->amount_due, 2, ',', '.'),
                    'amount_paid' => number_format($invoice->amount_paid, 2, ',', '.'),
                    'payment_status' => $invoice->payment_status,
                    'due_date' => $dueDate ? $dueDate->format('d.m.Y') : null,
                    'created_at' => $invoice->created_at ? $invoice->created_at->format('d.m.Y') : null,
                ];
            });

        // Get recent members (last 5)
        $recentMembers = Member::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'created_at' => $member->created_at ? $member->created_at->format('d.m.Y') : null,
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent_invoices' => $recentInvoices,
            'recent_members' => $recentMembers,
        ]);
    }
}
