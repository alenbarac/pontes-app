<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'date_of_birth' => 'required|date|before:today', // ✅ Ensure proper date validation
            'phone_number' => 'required|string|max:20',
            'email' => 'required|email|unique:members,email',
            'is_active' => 'boolean',
            'parent_contact' => 'nullable|string|max:20',
            'parent_email' => 'nullable|email|max:255',
            'invoice_email' => 'nullable|email|max:255',

            // ✅ Ensure relationships exist
            'workshop_id' => 'required|exists:workshops,id',
            'group_id' => 'nullable|exists:member_groups,id',
            'membership_plan_id' => 'nullable|exists:membership_plans,id',
        ];
    }
}
