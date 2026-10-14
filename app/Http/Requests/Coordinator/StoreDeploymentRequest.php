<?php

namespace App\Http\Requests\Coordinator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    public function rules(): array
    {
        $campusId = $this->user()->campus_id;

        return [
            // Either pick an existing HTE, or provide a new one's name to create it on the fly -
            // Partner HTE management doesn't have its own screen yet, so this covers that gap.
            'hte_id' => [
                'nullable', 'required_without:new_hte_name',
                Rule::exists('htes', 'id')->where('campus_id', $campusId),
            ],
            'new_hte_name' => ['nullable', 'required_without:hte_id', 'string', 'max:255'],
            'new_hte_address' => ['nullable', 'string', 'max:255'],

            'start_date' => ['nullable', 'date', 'required_if:status,active'],
            'end_date' => ['nullable', 'date', 'required_if:status,active', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['pending', 'active', 'terminated'])],
        ];
    }

    public function messages(): array
    {
        return [
            'hte_id.required_without' => 'Select an existing HTE or fill in a new one.',
            'new_hte_name.required_without' => 'Select an existing HTE or fill in a new one.',
            'end_date.after_or_equal' => 'End date cannot be before the start date.',
            'start_date.required_if' => 'A start date is required before activating a deployment.',
            'end_date.required_if' => 'An end date is required before activating a deployment.',
        ];
    }
}
