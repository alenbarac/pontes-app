<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Invoice::query()
            ->with('membershipPlan')
            ->where(function ($q) {
                $q->where('invoice_type', 'membership')
                    ->orWhereNull('invoice_type');
            })
            ->where('notes', 'like', 'Članarina za %')
            ->orderBy('id')
            ->each(function (Invoice $invoice) {
                $invoice->notes = Invoice::defaultMembershipNotes(
                    $invoice->membershipPlan,
                    $invoice->due_date
                );
                $invoice->saveQuietly();
            });
    }

    public function down(): void
    {
        Invoice::query()
            ->where(function ($q) {
                $q->where('notes', 'like', 'Godišnja članarina %')
                    ->orWhere('notes', 'like', 'Članarina 6 mjeseci%');
            })
            ->orderBy('id')
            ->each(function (Invoice $invoice) {
                $invoice->notes = 'Članarina za '.\Carbon\Carbon::parse($invoice->due_date)->format('m/Y');
                $invoice->saveQuietly();
            });
    }
};
