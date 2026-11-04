<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMemberDocumentsRequest extends FormRequest
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
            'document_template_id' => 'required|integer|exists:document_templates,id',
            'send_scope' => 'required|in:selected,all',
            'member_ids' => 'required_if:send_scope,selected|array',
            'member_ids.*' => 'integer|exists:members,id',
            'activity' => 'nullable|array',
            'activity.name' => 'nullable|string|max:255',
            'activity.start_date' => 'nullable|date',
            'activity.end_date' => 'nullable|date',
            'activity.location' => 'nullable|string|max:255',
            'activity.description' => 'nullable|string',
            'resend' => 'sometimes|boolean',
        ];
    }
}
