<?php

namespace App\Http\Requests\Admin;

use App\Models\Flag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFlagStatusRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in(['accepted', 'removed', 'partial', 'resolved', 'appealed', 'open']),
            ],
        ];
    }
}
