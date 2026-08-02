<?php

namespace App\Http\Requests\Snippets;

use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\VariablePreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSnippetCopyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $snippet = $this->route('snippet');

        return $snippet instanceof Snippet
            && $this->user()?->can('view', $snippet) === true;
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
            'event_uuid' => ['required', 'uuid'],
            'snippet_variation_id' => [
                'nullable',
                'integer',
                Rule::exists(SnippetVariation::class, 'id')->where('snippet_id', $snippet->id),
            ],
            'variable_preset_id' => [
                'nullable',
                'integer',
                Rule::exists(VariablePreset::class, 'id')->where('snippet_id', $snippet->id),
            ],
            'method' => ['required', Rule::in(['keyboard', 'button'])],
            'representation' => ['required', Rule::in(['source', 'rendered'])],
            'scope' => ['required', Rule::in(['selection', 'full'])],
            'selection_length' => ['required', 'integer', 'min:0', 'max:5000000'],
        ];
    }
}
