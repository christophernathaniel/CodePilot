<?php

namespace App\Actions\Snippets;

use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use App\Models\Folder;
use App\Models\Framework;
use App\Models\LibraryCategory;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\Tag;
use App\Models\User;
use App\Models\VariablePreset;
use App\Support\Snippets\GuideStepParser;
use App\Support\Snippets\LanguageCatalog;
use App\Support\Snippets\SnippetSectionParser;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final class BuildWorkspace
{
    /** @var array<string, array<string, true>> */
    private array $pinKeys = [];

    public function __construct(
        private readonly SnippetSectionParser $sectionParser,
        private readonly GuideStepParser $guideStepParser,
    ) {}

    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        $this->pinKeys = $user->pins()
            ->get(['pinnable_type', 'pinnable_key'])
            ->groupBy('pinnable_type')
            ->map(fn ($pins): array => array_fill_keys($pins->pluck('pinnable_key')->all(), true))
            ->all();

        $clipboardSessions = $user->clipboardSessions()
            ->withCount('clips')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $activeClipboardSession = $clipboardSessions->firstWhere('is_active', true);

        $activeClipboardSession?->load([
            'clips' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
        ]);

        $libraryCategories = $user->libraryCategories()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $projects = $user->projects()
            ->with([
                'folders' => fn ($query) => $query->orderBy('position')->orderBy('name'),
                'frameworks' => fn ($query) => $query->orderBy('name'),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->sortByDesc(fn (Project $project): bool => $this->isPinned('project', (string) $project->id))
            ->values();

        $snippets = $user->snippets()
            ->with([
                'variations' => fn ($query) => $query->orderBy('position')->orderBy('name'),
                'variablePresets' => fn ($query) => $query->orderBy('name'),
                'tags' => fn ($query) => $query->orderBy('name'),
                'frameworks' => fn ($query) => $query->orderBy('name'),
            ])
            ->withCount([
                'copyEvents as copies_30d' => fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30)),
                'copyEvents as copies_total',
            ])
            ->withMax('copyEvents as last_copied_at', 'created_at')
            ->orderBy('position')
            ->orderBy('filename')
            ->get()
            ->sortByDesc(fn (Snippet $snippet): bool => $this->isPinned('snippet', (string) $snippet->id))
            ->values();

        $maximumRecentCopies = max(1, (int) $snippets->max('copies_30d'));
        $snippetsByProject = $snippets->whereNotNull('project_id')->groupBy('project_id');

        foreach ($projects as $project) {
            $project->setRelation('snippets', $snippetsByProject->get($project->id, collect())->values());
        }

        $tags = $user->tags()->orderBy('name')->get();
        $frameworks = $user->frameworks()->orderBy('name')->get();
        $languageOptions = $this->languageOptions(
            array_values($snippets->map(fn (Snippet $snippet): string => $snippet->language)->all()),
        );

        return [
            'library_categories' => array_values(
                $libraryCategories
                    ->map(fn (LibraryCategory $libraryCategory): array => [
                        'id' => $libraryCategory->id,
                        'name' => $libraryCategory->name,
                        'position' => $libraryCategory->position,
                    ])
                    ->all(),
            ),
            'clipboard_sessions' => array_values(
                $clipboardSessions
                    ->map(fn (ClipboardSession $clipboardSession): array => $this->clipboardSession($clipboardSession))
                    ->all(),
            ),
            'projects' => array_values(
                $projects->map(fn (Project $project): array => $this->project($project, $maximumRecentCopies))->all(),
            ),
            'standalone_snippets' => array_values(
                $snippets
                    ->whereNull('project_id')
                    ->map(fn (Snippet $snippet): array => $this->snippet($snippet, $maximumRecentCopies))
                    ->all(),
            ),
            'language_options' => $languageOptions,
            'languages' => array_column($languageOptions, 'value'),
            'tags' => array_values($tags->map(fn (Tag $tag): array => $this->tag($tag))->all()),
            'frameworks' => array_values(
                $frameworks->map(fn (Framework $framework): array => $this->framework($framework))->all(),
            ),
            'pins' => [
                'snippet_ids' => $this->integerPinKeys(
                    'snippet',
                    array_values($snippets->map(fn (Snippet $snippet): int => $snippet->id)->all()),
                ),
                'project_ids' => $this->integerPinKeys(
                    'project',
                    array_values($projects->map(fn (Project $project): int => $project->id)->all()),
                ),
                'tag_ids' => $this->integerPinKeys(
                    'tag',
                    array_values($tags->map(fn (Tag $tag): int => $tag->id)->all()),
                ),
                'language_values' => $this->stringPinKeys('language', array_column($languageOptions, 'value')),
                'framework_ids' => $this->integerPinKeys(
                    'framework',
                    array_values($frameworks->map(fn (Framework $framework): int => $framework->id)->all()),
                ),
            ],
            'trash' => $this->trash($user),
        ];
    }

    /** @return array{projects: list<array<string, mixed>>, folders: list<array<string, mixed>>, snippets: list<array<string, mixed>>} */
    private function trash(User $user): array
    {
        $trashedProjects = $user->projects()
            ->onlyTrashed()
            ->latest('deleted_at')
            ->get();
        $trashedFolders = Folder::onlyTrashed()
            ->whereHas('project', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with('project:id,name')
            ->latest('deleted_at')
            ->get();
        $trashedFoldersById = $trashedFolders->keyBy('id');
        $visibleFolders = $trashedFolders->filter(
            fn (Folder $folder): bool => $folder->parent_id === null
                || ! $trashedFoldersById->has($folder->parent_id),
        );
        $trashedSnippets = $user->snippets()
            ->onlyTrashed()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('project_id')
                    ->orWhereHas('project');
            })
            ->with(['project:id,name', 'folder:id,name'])
            ->latest('deleted_at')
            ->get()
            ->filter(
                fn (Snippet $snippet): bool => $snippet->folder_id === null
                    || ! $trashedFoldersById->has($snippet->folder_id),
            );

        return [
            'projects' => $trashedProjects
                ->map(fn (Project $project): array => [
                    'type' => 'project',
                    'id' => $project->id,
                    'name' => $project->name,
                    'context' => match ($project->kind) {
                        Project::KIND_BUNDLE => 'Snippet bundle',
                        Project::KIND_GUIDE => 'Guide collection',
                        default => 'Project',
                    },
                    'deleted_at' => $this->timestamp($project->deleted_at),
                ])
                ->values()
                ->all(),
            'folders' => $visibleFolders
                ->map(fn (Folder $folder): array => [
                    'type' => 'folder',
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'context' => $folder->project->name,
                    'deleted_at' => $this->timestamp($folder->deleted_at),
                ])
                ->values()
                ->all(),
            'snippets' => $trashedSnippets
                ->map(fn (Snippet $snippet): array => [
                    'type' => 'snippet',
                    'id' => $snippet->id,
                    'name' => $snippet->filename,
                    'context' => $snippet->project?->name ?? 'Standalone',
                    'deleted_at' => $this->timestamp($snippet->deleted_at),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function clipboardSession(ClipboardSession $clipboardSession): array
    {
        return [
            'id' => $clipboardSession->id,
            'name' => $clipboardSession->name,
            'is_active' => $clipboardSession->is_active,
            'clips_count' => (int) $clipboardSession->getAttribute('clips_count'),
            'created_at' => $this->timestamp($clipboardSession->created_at),
            'updated_at' => $this->timestamp($clipboardSession->updated_at),
            'clips' => $clipboardSession->relationLoaded('clips')
                ? $clipboardSession->clips
                    ->map(fn (ClipboardClip $clipboardClip): array => $this->clipboardClip($clipboardClip))
                    ->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function clipboardClip(ClipboardClip $clipboardClip): array
    {
        $sourceFolders = $clipboardClip->getAttribute('source_folders');

        return [
            'id' => $clipboardClip->id,
            'content' => $clipboardClip->content,
            'language' => $clipboardClip->language,
            'representation' => $clipboardClip->representation,
            'created_at' => $this->timestamp($clipboardClip->created_at),
            'source' => [
                'snippet_id' => $clipboardClip->snippet_id,
                'variation_id' => $clipboardClip->snippet_variation_id,
                'title' => $clipboardClip->source_title,
                'filename' => $clipboardClip->source_filename,
                'project' => $clipboardClip->source_project,
                'folders' => is_array($sourceFolders) ? array_values($sourceFolders) : [],
                'variation' => $clipboardClip->source_variation,
                'line_start' => $clipboardClip->line_start,
                'line_end' => $clipboardClip->line_end,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function project(Project $project, int $maximumRecentCopies): array
    {
        return [
            'id' => $project->id,
            'library_category_id' => $project->library_category_id,
            'name' => $project->name,
            'kind' => $project->kind,
            'description' => $project->description,
            'is_pinned' => $this->isPinned('project', (string) $project->id),
            'frameworks' => $project->frameworks
                ->map(fn (Framework $framework): array => $this->framework($framework))
                ->all(),
            'folders' => $project->folders
                ->map(fn (Folder $folder): array => [
                    'id' => $folder->id,
                    'project_id' => $folder->project_id,
                    'parent_id' => $folder->parent_id,
                    'name' => $folder->name,
                    'position' => $folder->position,
                ])->all(),
            'snippets' => $project->snippets
                ->map(fn (Snippet $snippet): array => $this->snippet($snippet, $maximumRecentCopies))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function snippet(Snippet $snippet, int $maximumRecentCopies): array
    {
        $recentCopies = (int) $snippet->getAttribute('copies_30d');
        $totalCopies = (int) $snippet->getAttribute('copies_total');
        $relativeScore = $recentCopies / $maximumRecentCopies;
        $relativeIndicator = match (true) {
            $relativeScore < 0.34 => 1,
            $relativeScore < 0.67 => 2,
            default => 3,
        };
        $volumeIndicator = match (true) {
            $recentCopies < 3 => 1,
            $recentCopies < 10 => 2,
            default => 3,
        };
        $indicator = match (true) {
            $recentCopies === 0 && $totalCopies > 0 => -1,
            $recentCopies === 0 => 0,
            default => min($relativeIndicator, $volumeIndicator),
        };

        return [
            'id' => $snippet->id,
            'project_id' => $snippet->project_id,
            'folder_id' => $snippet->folder_id,
            'title' => $snippet->title,
            'filename' => $snippet->filename,
            'language' => $snippet->language,
            'content_type' => $snippet->content_type,
            'description' => $snippet->description,
            'position' => $snippet->position,
            'is_favourite' => $snippet->is_favourite,
            'is_pinned' => $this->isPinned('snippet', (string) $snippet->id),
            'last_opened_at' => $this->timestamp($snippet->last_opened_at),
            'updated_at' => $this->timestamp($snippet->updated_at),
            'usage' => [
                'copies_30d' => $recentCopies,
                'copies_total' => $totalCopies,
                'last_copied_at' => $this->timestamp($snippet->getAttribute('last_copied_at')),
                'relative_score' => round($relativeScore, 3),
                'indicator' => $indicator,
            ],
            'variations' => $snippet->variations
                ->map(fn (SnippetVariation $variation): array => [
                    'id' => $variation->id,
                    'name' => $variation->name,
                    'content' => $variation->content,
                    'sections' => $this->sectionParser->parse($variation->content),
                    'guide_steps' => $snippet->content_type === Snippet::CONTENT_TYPE_GUIDE
                        ? $this->guideStepParser->parse($variation->content)
                        : [],
                    'position' => $variation->position,
                    'is_default' => $variation->is_default,
                    'updated_at' => $this->timestamp($variation->updated_at),
                ])->all(),
            'presets' => $snippet->variablePresets
                ->map(fn (VariablePreset $preset): array => [
                    'id' => $preset->id,
                    'name' => $preset->name,
                    'values' => $preset->values,
                ])->all(),
            'tags' => $snippet->tags
                ->map(fn (Tag $tag): array => $this->tag($tag))
                ->all(),
            'frameworks' => $snippet->frameworks
                ->map(fn (Framework $framework): array => $this->framework($framework))
                ->all(),
        ];
    }

    /** @return array{id: int, name: string, slug: string, color: string, is_pinned: bool} */
    private function tag(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
            'is_pinned' => $this->isPinned('tag', (string) $tag->id),
        ];
    }

    /** @return array{id: int, name: string, slug: string, color: string, is_pinned: bool} */
    private function framework(Framework $framework): array
    {
        return [
            'id' => $framework->id,
            'name' => $framework->name,
            'slug' => $framework->slug,
            'color' => $framework->color,
            'is_pinned' => $this->isPinned('framework', (string) $framework->id),
        ];
    }

    /**
     * @param  list<string>  $storedLanguages
     * @return list<array{value: string, label: string, aliases: list<string>, syntax: string, extensions: list<string>, is_pinned: bool}>
     */
    private function languageOptions(array $storedLanguages): array
    {
        $options = LanguageCatalog::options();
        $knownValues = array_fill_keys(LanguageCatalog::values(), true);

        foreach (array_unique($storedLanguages) as $language) {
            if ($language === '' || isset($knownValues[$language])) {
                continue;
            }

            $options[] = [
                'value' => $language,
                'label' => $language,
                'aliases' => [],
                'syntax' => $language,
                'extensions' => [],
            ];
        }

        return array_map(fn (array $option): array => [
            ...$option,
            'is_pinned' => $this->isPinned('language', $option['value']),
        ], $options);
    }

    private function isPinned(string $type, string $key): bool
    {
        return isset($this->pinKeys[$type][$key]);
    }

    /**
     * @param  list<int>  $allowed
     * @return list<int>
     */
    private function integerPinKeys(string $type, array $allowed): array
    {
        $allowedKeys = array_fill_keys(array_map('strval', $allowed), true);

        return array_values(array_map(
            'intval',
            array_filter(array_keys($this->pinKeys[$type] ?? []), fn (string $key): bool => isset($allowedKeys[$key])),
        ));
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function stringPinKeys(string $type, array $allowed): array
    {
        $allowedKeys = array_fill_keys($allowed, true);

        return array_values(array_filter(
            array_keys($this->pinKeys[$type] ?? []),
            fn (string $key): bool => isset($allowedKeys[$key]),
        ));
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
