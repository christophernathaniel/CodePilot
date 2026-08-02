<?php

namespace App\Actions\Snippets;

use App\Models\Folder;
use App\Models\Framework;
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

        $projects = $user->projects()
            ->with([
                'folders' => fn ($query) => $query->orderBy('position')->orderBy('name'),
                'frameworks' => fn ($query) => $query->orderBy('name'),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

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
            ->get();

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
        ];
    }

    /** @return array<string, mixed> */
    private function project(Project $project, int $maximumRecentCopies): array
    {
        return [
            'id' => $project->id,
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
