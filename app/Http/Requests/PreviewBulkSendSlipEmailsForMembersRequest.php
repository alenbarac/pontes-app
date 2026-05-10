<?php

namespace App\Http\Requests;

use App\Support\MonthString;
use Illuminate\Foundation\Http\FormRequest;

class PreviewBulkSendSlipEmailsForMembersRequest extends FormRequest
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
            // Cap left generous on preview because it's read-only.
            'member_ids' => ['required', 'array', 'min:1', 'max:500'],
            'member_ids.*' => ['integer', 'exists:members,id'],
            'month' => ['required', 'regex:'.MonthString::REGEX],
        ];
    }
}
