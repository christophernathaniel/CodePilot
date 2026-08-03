<?php

namespace App\Http\Requests\Frameworks;

use App\Models\Framework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreFrameworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Framework::class) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Framework::class)->where('user_id', $this->user()->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = Str::of($this->string('name'))->trim()->squish()->toString();

        $this->merge([
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }
}
