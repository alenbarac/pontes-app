<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    
    public function index(Request $request)
{
    $perPage = $request->get('per_page', 10);
    $filter = $request->get('filter', '');

    $query = Invoice::with(['member', 'workshop', 'membershipPlan'])
        ->when($filter, function ($query, $filter) {
            $query->where(function ($q) use ($filter) {
                $q->whereHas('member', fn($q) =>
                    $q->where('first_name', 'like', "%$filter%")
                      ->orWhere('last_name', 'like', "%$filter%")
                )
                ->orWhereHas('workshop', fn($q) =>
                    $q->where('name', 'like', "%$filter%")
                )
                ->orWhere('reference_code', 'like', "%$filter%")
                ->orWhere('payment_status', 'like', "%$filter%");
            });
        })
        ->orderByDesc('due_date');

    $invoices = $query->paginate($perPage)->withQueryString();

    return Inertia::render('Invoices/Index', [
        'invoices' => $invoices,
        'pagination' => [
            'current_page' => $invoices->currentPage(),
            'per_page' => $invoices->perPage(),
            'total' => $invoices->total(),
            'last_page' => $invoices->lastPage(),
        ],
        'filter' => $filter,
    ]);
}
}
