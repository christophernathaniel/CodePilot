<?php

namespace App\Http\Requests\LibraryCategories;

use App\Models\LibraryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateLibraryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $libraryCategory = $this->route('libraryCategory');

        return $libraryCategory instanceof LibraryCategory
            && $this->user()?->can('update', $libraryCategory) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        /** @var LibraryCategory $libraryCategory */
        $libraryCategory = $this->route('libraryCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(LibraryCategory::class)
                    ->where('user_id', $this->user()->id)
                    ->ignore($libraryCategory),
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
