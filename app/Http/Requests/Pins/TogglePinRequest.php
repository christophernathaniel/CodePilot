<?php

namespace App\Http\Requests\Pins;

use App\Models\Framework;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Support\Snippets\LanguageCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TogglePinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'pinnable_type' => ['required', Rule::in(['snippet', 'project', 'tag', 'language', 'framework'])],
            'pinnable_key' => ['required', 'string', 'max:100'],
            'pinned' => ['required', 'boolean'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->string('pinnable_type')->toString();
            $key = $this->string('pinnable_key')->toString();
            $userId = $this->user()?->id;

            $exists = match ($type) {
                'snippet' => ctype_digit($key) && Snippet::query()->whereKey((int) $key)->where('user_id', $userId)->exists(),
                'project' => ctype_digit($key) && Project::query()->whereKey((int) $key)->where('user_id', $userId)->exists(),
                'tag' => ctype_digit($key) && Tag::query()->whereKey((int) $key)->where('user_id', $userId)->exists(),
                'framework' => ctype_digit($key) && Framework::query()->whereKey((int) $key)->where('user_id', $userId)->exists(),
                'language' => in_array($key, LanguageCatalog::values(), true),
                default => false,
            };

            if (! $exists) {
                $validator->errors()->add('pinnable_key', __('The selected item is unavailable.'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $type = strtolower(trim($this->string('pinnable_type')->toString()));
        $key = strtolower(trim($this->string('pinnable_key')->toString()));

        $this->merge([
            'pinnable_type' => $type,
            'pinnable_key' => $type === 'language'
                ? (LanguageCatalog::normalize($key) ?? $key)
                : $key,
        ]);
    }
}
