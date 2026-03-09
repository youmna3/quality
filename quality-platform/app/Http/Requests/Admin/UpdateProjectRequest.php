<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('update', $project);
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::in(['DEMI', 'DECI']),
                Rule::unique('projects', 'code')->ignore($project->id),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
