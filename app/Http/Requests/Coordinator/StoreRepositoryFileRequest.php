<?php

namespace App\Http\Requests\Coordinator;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCoordinator() ?? false;
    }

    public function rules(): array
    {
        $campusId = $this->user()->campus_id;

        return [
            'student_id' => [
                'required',
                Rule::exists('students', 'id')->where('campus_id', $campusId),
            ],
            'document_requirement_id' => [
                'required',
                Rule::exists('document_requirements', 'id')->where('campus_id', $campusId),
            ],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
