<?php

namespace App\Http\Requests\Tutor;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Complaint::class);
    }

    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date'],
            'slot' => ['required', 'string', 'max:100'],
            'group_code' => ['required', 'string', 'max:100'],
            'issue_type' => ['required', Rule::in(['session', 'student'])],
            'complaint_text' => ['required', 'string', 'max:5000'],
        ];
    }
}
