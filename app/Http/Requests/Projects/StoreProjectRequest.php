<?php

namespace App\Http\Requests\Projects;

use App\Models\LibraryCategory;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Project::class)->where('user_id', $this->user()->id),
            ],
            'library_category_id' => [
                'nullable',
                'integer',
                Rule::exists(LibraryCategory::class, 'id')->where('user_id', $this->user()->id),
            ],
            'kind' => ['required', Rule::in(Project::KINDS)],
            'description' => ['nullable', 'string', 'max:5000'],
            'frameworks' => ['sometimes', 'array', 'max:20'],
            'frameworks.*' => ['required', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'name' => Str::of($this->string('name'))->trim()->squish()->toString(),
        ];

        if ($this->exists('frameworks')) {
            $frameworks = $this->input('frameworks', []);
            $normalized['frameworks'] = collect(is_array($frameworks) ? $frameworks : [])
                ->filter(fn (mixed $framework): bool => is_string($framework))
                ->map(fn (string $framework): string => Str::of($framework)->trim()->squish()->toString())
                ->filter()
                ->values()
                ->all();
        }

        $this->merge($normalized);
    }
}
