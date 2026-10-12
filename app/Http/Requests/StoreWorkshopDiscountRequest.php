<?php

namespace App\Http\Requests;

use App\Models\MembershipPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkshopDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workshop_id' => ['required', 'exists:workshops,id'],
            'membership_plan_id' => [
                'required',
                Rule::exists('membership_plans', 'id')->where(
                    fn ($query) => $query->where('workshop_id', $this->input('workshop_id'))
                ),
            ],
            'percent' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $plan = MembershipPlan::query()->find($this->input('membership_plan_id'));

            if ($plan && $plan->isDiscounted()) {
                $validator->errors()->add(
                    'membership_plan_id',
                    'Popust se može dodati samo na osnovni plan.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workshop_id.required' => 'Odaberite radionicu.',
            'workshop_id.exists' => 'Odabrana radionica ne postoji.',
            'membership_plan_id.required' => 'Odaberite plan članarine.',
            'membership_plan_id.exists' => 'Odabrani plan ne pripada ovoj radionici.',
            'percent.required' => 'Unesite postotak popusta.',
            'percent.integer' => 'Postotak popusta mora biti cijeli broj.',
            'percent.min' => 'Postotak popusta mora biti između 1 i 99.',
            'percent.max' => 'Postotak popusta mora biti između 1 i 99.',
        ];
    }
}
