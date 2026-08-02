<?php

namespace App\Http\Requests\Folders;

use App\Models\Folder;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveFolderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $folder = $this->route('folder');

        return $folder instanceof Folder
            && $this->user()?->can('update', $folder) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;
        $projectId = $this->input('project_id');

        return [
            'project_id' => [
                'required',
                'integer',
                Rule::exists(Project::class, 'id')->where('user_id', $userId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Folder::class, 'id')->where('project_id', $projectId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->integer('project_id'),
            'parent_id' => $this->integer('parent_id') ?: null,
        ]);
    }
}
