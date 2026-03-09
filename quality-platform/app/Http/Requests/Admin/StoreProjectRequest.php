<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'in:DEMI,DECI', 'unique:projects,code'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
