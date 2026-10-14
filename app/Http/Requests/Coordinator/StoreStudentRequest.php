<?php

namespace App\Http\Requests\Coordinator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    public function rules(): array
    {
        // campus_id is intentionally NOT a submitted field - it's derived from
        // the logged-in coordinator's own campus (see StudentController), so a
        // coordinator can never provision an intern into a campus they don't manage.
        $campusId = $this->user()->campus_id;

        return [
            'id_number' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'id_number')->where('campus_id', $campusId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'program_id' => [
                'required',
                Rule::exists('programs', 'id')->where('campus_id', $campusId),
            ],
            'instructor_id' => [
                'nullable',
                Rule::exists('instructors', 'id')->where('campus_id', $campusId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_number.unique' => 'An intern with this ID number already exists at your campus.',
            'program_id.exists' => 'Select a program offered at your campus.',
            'instructor_id.exists' => 'Select an instructor from your campus.',
        ];
    }
}
