<?php

namespace App\Http\Requests\Admin;

use App\Models\Tutor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTutorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Tutor::class);
    }

    public function rules(): array
    {
        return [
            'tutor_code' => ['required', 'string', 'max:50', 'unique:tutors,tutor_code'],
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
