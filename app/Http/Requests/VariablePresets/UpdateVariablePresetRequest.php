<?php

namespace App\Http\Requests\VariablePresets;

use App\Models\Snippet;
use App\Models\VariablePreset;
use App\Support\Snippets\TemplateVariableParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVariablePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variablePreset = $this->route('variablePreset');

        return $variablePreset instanceof VariablePreset
            && $this->user()?->can('update', $variablePreset) === true;
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
        /** @var VariablePreset $variablePreset */
        $variablePreset = $this->route('variablePreset');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(VariablePreset::class)
                    ->where('snippet_id', $snippet->id)
                    ->ignore($variablePreset),
            ],
            'values' => ['required', 'array', 'max:100'],
            'values.*' => ['string', 'max:10000'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateVariableNames($validator)];
    }

    protected function prepareForValidation(): void
    {
        $values = $this->input('values', []);

        $this->merge([
            'name' => Str::of($this->string('name'))->trim()->squish()->toString(),
            'values' => is_array($values)
                ? collect($values)->map(fn (mixed $value): mixed => $value ?? '')->all()
                : $values,
        ]);
    }

    private function validateVariableNames(Validator $validator): void
    {
        if ($validator->errors()->has('values')) {
            return;
        }

        /** @var Snippet $snippet */
        $snippet = $this->route('snippet');
        $parser = new TemplateVariableParser;
        $variableNames = $snippet->variations()
            ->pluck('content')
            ->flatMap(fn (string $content): array => array_keys($parser->parse($content)))
            ->unique()
            ->all();
        $unknownNames = array_diff(array_keys($this->array('values')), $variableNames);

        if ($unknownNames !== []) {
            $validator->errors()->add(
                'values',
                'Unknown template variables: '.implode(', ', $unknownNames).'.',
            );
        }
    }
}
