<?php

namespace App\Http\Requests\SnippetVariations;

use App\Models\Snippet;
use App\Models\SnippetVariation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSnippetVariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $snippetVariation = $this->route('snippetVariation');

        return $snippetVariation instanceof SnippetVariation
            && $this->user()?->can('update', $snippetVariation) === true;
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
        /** @var SnippetVariation $snippetVariation */
        $snippetVariation = $this->route('snippetVariation');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(SnippetVariation::class)
                    ->where('snippet_id', $snippet->id)
                    ->ignore($snippetVariation),
            ],
            'content' => ['present', 'string', 'max:5000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'name' => Str::of($this->string('name'))->trim()->squish()->toString(),
        ];

        if ($this->exists('content') && $this->input('content') === null) {
            $normalized['content'] = '';
        }

        $this->merge($normalized);
    }
}
