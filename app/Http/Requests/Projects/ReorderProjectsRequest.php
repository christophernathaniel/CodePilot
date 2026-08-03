<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'project_ids' => ['present', 'array', 'list'],
            'project_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if ($user === null) {
                return;
            }

            /** @var list<int|string> $submittedProjectIds */
            $submittedProjectIds = $this->input('project_ids', []);
            $submittedProjectIds = array_map(
                static fn (int|string $projectId): int => (int) $projectId,
                $submittedProjectIds,
            );
            $ownedProjectIds = Project::query()
                ->whereBelongsTo($user)
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            sort($submittedProjectIds);

            if ($submittedProjectIds !== $ownedProjectIds) {
                $validator->errors()->add(
                    'project_ids',
                    __('The project order is out of date. Refresh the workspace and try again.'),
                );
            }
        }];
    }
}
