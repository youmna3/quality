<?php

namespace App\Http\Requests\Admin;

use App\Models\Flag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFlagObjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Flag $flag */
        $flag = $this->route('flag');

        return $this->user()->can('update', $flag);
    }

    public function rules(): array
    {
        return [
            'objection_status' => ['required', Rule::in(['pending', 'accepted', 'rejected'])],
            'objection_response' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

