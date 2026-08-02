<?php

namespace App\Http\Requests\Snippets;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveSnippetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $snippet = $this->route('snippet');

        return $snippet instanceof Snippet
            && $this->user()?->can('update', $snippet) === true;
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
        $folderId = $this->input('folder_id');

        return [
            'project_id' => [
                'nullable',
                'integer',
                Rule::requiredIf($folderId !== null),
                Rule::exists(Project::class, 'id')->where('user_id', $userId),
            ],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists(Folder::class, 'id')->where('project_id', $projectId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->integer('project_id') ?: null,
            'folder_id' => $this->integer('folder_id') ?: null,
        ]);
    }
}
