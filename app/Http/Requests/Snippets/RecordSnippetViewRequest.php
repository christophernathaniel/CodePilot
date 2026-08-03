<?php

namespace App\Http\Requests\Snippets;

use App\Models\Snippet;
use Illuminate\Foundation\Http\FormRequest;

class RecordSnippetViewRequest extends FormRequest
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
        return [
            'event_uuid' => ['required', 'uuid'],
        ];
    }
}
