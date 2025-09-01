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
public function updateStatus(Request $request, Invoice $invoice)
{
    $validated = $request->validate([
        'status' => ['required', 'in:Plaćeno,Otvoreno'],
    ]);

    $status = $validated['status'];

    if ($status === 'Plaćeno') {
        // If you hit this endpoint instead of markPaid, set amount_paid to full
        $invoice->amount_paid = $invoice->amount_due;
    } else { // Otvoreno
        $invoice->amount_paid = 0;
    }
    
    $invoice->payment_status = $status;
    $invoice->save();

    return redirect()->route('invoices.index')->with('success', 'Status uspješno promijenjen.');
}


// app/Http/Controllers/InvoiceController.php

public function markAsPaid(Request $request, Invoice $invoice)
{
    if ($invoice->payment_status === 'Plaćeno') {
        return redirect()->route('invoices.index')->with('info', 'Račun je već plaćen.');
    }

    // Optional: allow override amount; by default pay in full
    $validated = $request->validate([
        'amount_paid' => ['nullable', 'numeric', 'min:0'],
    ]);

    $invoice->amount_paid    = $validated['amount_paid'] ?? $invoice->amount_due;
    $invoice->payment_status = 'Plaćeno';
    $invoice->save();

    return redirect()->route('invoices.index')->with('success', 'Račun označen kao plaćen.');
}



}
