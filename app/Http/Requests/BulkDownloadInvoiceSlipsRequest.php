<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDownloadInvoiceSlipsRequest extends FormRequest
{
    /**
     * Synchronous PDF generation is bounded so a single HTTP request stays
     * under a reasonable web-server / proxy timeout.
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_ids.required' => 'Odaberite barem jedan račun.',
            'invoice_ids.min' => 'Odaberite barem jedan račun.',
            'invoice_ids.max' => 'Možete preuzeti najviše '.self::MAX_BATCH.' uplatnica odjednom.',
        ];
    }
}
