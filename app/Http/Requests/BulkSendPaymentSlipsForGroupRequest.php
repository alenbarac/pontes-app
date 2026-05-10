<?php

namespace App\Http\Requests;

use App\Support\MonthString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSendPaymentSlipsForGroupRequest extends FormRequest
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
            'month' => ['required', 'regex:'.MonthString::REGEX],
            'send_scope' => ['required', Rule::in(['selected', 'all'])],
            'member_ids' => ['exclude_if:send_scope,all', 'required_if:send_scope,selected', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ];
    }
}
