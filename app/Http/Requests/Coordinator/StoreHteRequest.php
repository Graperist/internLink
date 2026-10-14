<?php

namespace App\Http\Requests\Coordinator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'representative_position' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'moa_status' => ['required', Rule::in(['pending', 'active', 'expired'])],

            // DTR location security
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['nullable', 'numeric', 'between:10,1000'],
            'max_gps_accuracy_m' => ['nullable', 'numeric', 'between:5,500'],
        ];
    }
}
