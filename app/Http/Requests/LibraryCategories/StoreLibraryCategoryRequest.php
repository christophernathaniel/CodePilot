<?php

namespace App\Http\Requests\LibraryCategories;

use App\Models\LibraryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreLibraryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LibraryCategory::class) === true;
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
                Rule::unique(LibraryCategory::class)->where('user_id', $this->user()->id),
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
