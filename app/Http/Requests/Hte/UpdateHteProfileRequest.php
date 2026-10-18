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
     * they're the ones physically there.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
