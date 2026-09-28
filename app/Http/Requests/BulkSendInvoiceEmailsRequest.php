<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSendInvoiceEmailsRequest extends FormRequest
{
    /**
     * Selected invoice IDs per request. Sending itself is queued.
     * Group "send all" is capped separately at
     * PaymentSlipEmailService::MAX_INVOICES_PER_SEND.
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
            'resend' => ['sometimes', 'boolean'],
        ];
    }
}
