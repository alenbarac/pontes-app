<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSendInvoiceEmailsRequest extends FormRequest
{
    /**
     * Synchronous send is bounded so a single HTTP request stays under any
     * reasonable web-server / proxy timeout. Larger groups should be sent
     * in successive batches from the UI.
     */
    public const MAX_BATCH = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
        ];
    }
}
