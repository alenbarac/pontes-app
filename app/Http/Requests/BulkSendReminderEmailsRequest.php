<?php

namespace App\Http\Requests;

use App\Services\PaymentSlipEmailService;
use Illuminate\Foundation\Http\FormRequest;

class BulkSendReminderEmailsRequest extends FormRequest
{
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
            'invoice_ids' => ['required', 'array', 'min:1', 'max:'.PaymentSlipEmailService::MAX_INVOICES_PER_SEND],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'resend' => ['sometimes', 'boolean'],
        ];
    }
}
