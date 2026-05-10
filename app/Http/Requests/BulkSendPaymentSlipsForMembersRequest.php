<?php

namespace App\Http\Requests;

use App\Support\MonthString;
use Illuminate\Foundation\Http\FormRequest;

class BulkSendPaymentSlipsForMembersRequest extends FormRequest
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
            'member_ids' => ['required', 'array', 'min:1', 'max:'.BulkSendInvoiceEmailsRequest::MAX_BATCH],
            'member_ids.*' => ['integer', 'exists:members,id'],
            'month' => ['required', 'regex:'.MonthString::REGEX],
        ];
    }
}
