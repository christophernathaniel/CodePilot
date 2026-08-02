<?php

namespace App\Http\Requests\Snippets;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Support\Snippets\LanguageCatalog;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSnippetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $routeProject = $this->route('project');

        if ($routeProject instanceof Project) {
            return $this->user()?->can('update', $routeProject) === true;
        }

        return $this->user()?->can('create', Snippet::class) === true;
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
        $locationKey = SnippetLocation::key(
            is_int($projectId) ? $projectId : null,
            is_int($folderId) ? $folderId : null,
        );

        return [
            'title' => ['required', 'string', 'max:255'],
            'filename' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Snippet::class)->where(function (Builder $query) use ($userId, $locationKey): void {
                    $query
                        ->where('user_id', $userId)
                        ->where('location_key', $locationKey);
                }),
            ],
            'language' => ['required', 'string', Rule::in(LanguageCatalog::values())],
            'content_type' => ['required', 'string', Rule::in(Snippet::CONTENT_TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
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
            'content' => ['present', 'string', 'max:5000000'],
            'tags' => ['present', 'array', 'max:30'],
            'tags.*' => ['required', 'string', 'max:50'],
            'frameworks' => ['present', 'array', 'max:20'],
            'frameworks.*' => ['required', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags', []);
        $frameworks = $this->input('frameworks', []);
        $language = LanguageCatalog::normalize($this->string('language')->toString());
        $routeProject = $this->route('project');
        $projectId = $this->integer('project_id')
            ?: ($routeProject instanceof Project ? $routeProject->id : null);

        $normalized = [
            'title' => Str::of($this->string('title'))->trim()->squish()->toString(),
            'filename' => Str::of($this->string('filename'))->trim()->toString(),
            'language' => $language ?? Str::of($this->string('language'))->trim()->lower()->toString(),
            'content_type' => $this->string('content_type', Snippet::CONTENT_TYPE_SNIPPET)
                ->trim()
                ->lower()
                ->toString(),
            'project_id' => $projectId,
            'folder_id' => $this->input('folder_id') ?: null,
            'tags' => collect(is_array($tags) ? $tags : [])
                ->filter(fn (mixed $tag): bool => is_string($tag))
                ->map(fn (string $tag): string => Str::of($tag)->trim()->squish()->toString())
                ->filter()
                ->values()
                ->all(),
            'frameworks' => collect(is_array($frameworks) ? $frameworks : [])
                ->filter(fn (mixed $framework): bool => is_string($framework))
                ->map(fn (string $framework): string => Str::of($framework)->trim()->squish()->toString())
                ->filter()
                ->values()
                ->all(),
        ];

        if ($this->exists('content') && $this->input('content') === null) {
            $normalized['content'] = '';
        }

        $this->merge($normalized);
    }
}
