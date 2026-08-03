<?php

namespace App\Http\Requests\ClipboardClips;

use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClipboardClipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ClipboardClip::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;
        $snippetId = $this->input('snippet_id');

        return [
            'clipboard_session_id' => [
                'nullable',
                'integer',
                Rule::exists(ClipboardSession::class, 'id')->where('user_id', $userId),
            ],
            'snippet_id' => [
                'nullable',
                'required_with:snippet_variation_id',
                'integer',
                Rule::exists(Snippet::class, 'id')->where('user_id', $userId),
            ],
            'snippet_variation_id' => [
                'nullable',
                'required_with:snippet_id',
                'integer',
                Rule::exists(SnippetVariation::class, 'id')->where('snippet_id', $snippetId),
            ],
            'content' => ['present', 'string', 'min:1', 'max:5000000'],
            'representation' => ['required', Rule::in(['source', 'rendered'])],
            'line_start' => ['required', 'integer', 'min:1'],
            'line_end' => ['required', 'integer', 'min:1', 'gte:line_start'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->exists('content') && $this->input('content') === '') {
                $validator->errors()->add('content', __('The content field must contain at least one character.'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $attributes = [
            'representation' => $this->string('representation')->trim()->lower()->toString(),
        ];

        if ($this->exists('clipboard_session_id')) {
            $attributes['clipboard_session_id'] = $this->input('clipboard_session_id') === null
                || $this->input('clipboard_session_id') === ''
                    ? null
                    : $this->integer('clipboard_session_id');
        }

        if ($this->exists('snippet_id')) {
            $attributes['snippet_id'] = $this->input('snippet_id') === null
                || $this->input('snippet_id') === ''
                    ? null
                    : $this->integer('snippet_id');
        }

        if ($this->exists('snippet_variation_id')) {
            $attributes['snippet_variation_id'] = $this->input('snippet_variation_id') === null
                || $this->input('snippet_variation_id') === ''
                    ? null
                    : $this->integer('snippet_variation_id');
        }

        $this->merge($attributes);
    }
}
