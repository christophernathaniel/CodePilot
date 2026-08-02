<?php

namespace App\Http\Requests\Snippets;

use App\Models\Snippet;
use App\Support\Snippets\LanguageCatalog;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSnippetRequest extends FormRequest
{
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
        /** @var Snippet $snippet */
        $snippet = $this->route('snippet');

        return [
            'title' => ['required', 'string', 'max:255'],
            'filename' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Snippet::class)->where(function (Builder $query) use ($snippet): void {
                    $query
                        ->where('user_id', $snippet->user_id)
                        ->where('location_key', $snippet->location_key);
                })->ignore($snippet),
            ],
            'language' => ['required', 'string', Rule::in(LanguageCatalog::values())],
            'content_type' => ['required', 'string', Rule::in(Snippet::CONTENT_TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
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
        $snippet = $this->route('snippet');

        $normalized = [
            'title' => Str::of($this->string('title'))->trim()->squish()->toString(),
            'filename' => Str::of($this->string('filename'))->trim()->toString(),
            'language' => $language ?? Str::of($this->string('language'))->trim()->lower()->toString(),
            'content_type' => $this->string(
                'content_type',
                $snippet instanceof Snippet ? $snippet->content_type : Snippet::CONTENT_TYPE_SNIPPET,
            )->trim()->lower()->toString(),
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

        $this->merge($normalized);
    }
}
