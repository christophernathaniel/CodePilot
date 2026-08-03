<?php

namespace App\Http\Requests\ClipboardSessions;

use App\Models\ClipboardSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreClipboardSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ClipboardSession::class) === true;
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
                'nullable',
                'string',
                'max:80',
                Rule::unique(ClipboardSession::class)->where('user_id', $this->user()?->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = Str::of($this->string('name'))->trim()->squish()->toString();

        $this->merge([
            'name' => $name === '' ? null : $name,
        ]);
    }
}
