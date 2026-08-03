<?php

namespace App\Http\Requests\ClipboardSessions;

use App\Models\ClipboardSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateClipboardSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $clipboardSession = $this->route('clipboardSession');

        return $clipboardSession instanceof ClipboardSession
            && $this->user()?->can('update', $clipboardSession) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var ClipboardSession $clipboardSession */
        $clipboardSession = $this->route('clipboardSession');

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique(ClipboardSession::class)
                    ->where('user_id', $this->user()?->id)
                    ->ignore($clipboardSession),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of($this->string('name'))->trim()->squish()->toString(),
        ]);
    }
}
