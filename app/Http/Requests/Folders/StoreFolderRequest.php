<?php

namespace App\Http\Requests\Folders;

use App\Models\Folder;
use App\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('update', $project) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $parentId = $this->input('parent_id');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Folder::class)->where(function (Builder $query) use ($project, $parentId): void {
                    $query->where('project_id', $project->id);

                    $parentId === null
                        ? $query->whereNull('parent_id')
                        : $query->where('parent_id', $parentId);
                }),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Folder::class, 'id')->where('project_id', $project->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of($this->string('name'))->trim()->squish()->toString(),
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }
}
