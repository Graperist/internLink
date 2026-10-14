<?php

namespace App\Http\Requests\Hte;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isHteRepresentative() ?? false;
    }

    /**
     * Deliberately excludes moa_status - the partnership approval state
     * stays coordinator-only. Everything else here is company/site detail
     * the HTE's own on-site supervisor is the actual authority on, since
     * they're the ones physically there (same DTR location-security
     * fields as Coordinator\StoreHteRequest, minus moa_status).
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],

            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['nullable', 'numeric', 'between:10,1000'],
            'max_gps_accuracy_m' => ['nullable', 'numeric', 'between:5,500'],
        ];
    }
}
