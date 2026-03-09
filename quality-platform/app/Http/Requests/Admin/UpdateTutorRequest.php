<?php

namespace App\Http\Requests\Admin;

use App\Models\Tutor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTutorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tutor $tutor */
        $tutor = $this->route('tutor');

        return $this->user()->can('update', $tutor);
    }

    public function rules(): array
    {
        /** @var Tutor $tutor */
        $tutor = $this->route('tutor');

        return [
            'tutor_code' => ['required', 'string', 'max:50', Rule::unique('tutors', 'tutor_code')->ignore($tutor->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', Rule::in(['DEMI', 'DECI', 'demi', 'deci'])],
            'mentor_name' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:50'],
            'zoom_email' => ['nullable', 'email', 'max:255'],
            'zoom_password' => ['nullable', 'string', 'max:255'],
            'dashboard_password' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
