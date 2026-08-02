<?php

namespace App\Http\Requests\Folders;

use App\Models\Folder;
use App\Models\Project;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFolderRequest extends FormRequest
{
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
        /** @var Project $project */
        $project = $this->route('project');
        /** @var Folder $folder */
        $folder = $this->route('folder');
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
                })->ignore($folder),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Folder::class, 'id')->where('project_id', $project->id),
            ],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('parent_id') || $this->input('parent_id') === null) {
                return;
            }

            /** @var Folder $folder */
            $folder = $this->route('folder');
            $candidateId = (int) $this->input('parent_id');
            $visited = [];

            while ($candidateId > 0 && ! isset($visited[$candidateId])) {
                if ($candidateId === $folder->id) {
                    $validator->errors()->add('parent_id', 'A folder cannot be moved inside itself or one of its descendants.');

                    return;
                }

                $visited[$candidateId] = true;
                $parentId = Folder::query()
                    ->where('project_id', $folder->project_id)
                    ->whereKey($candidateId)
                    ->value('parent_id');
                $candidateId = $parentId === null ? 0 : (int) $parentId;
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of($this->string('name'))->trim()->squish()->toString(),
            'parent_id' => $this->input('parent_id') ?: null,
        ]);
    }
}
