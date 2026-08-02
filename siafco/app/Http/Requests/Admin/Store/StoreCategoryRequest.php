<?php

namespace App\Http\Requests\Admin\Store;

use App\Models\StoreCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage-products') === true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $slug = trim((string) $this->input('slug'));

        $this->merge([
            'name' => $name,
            'slug' => Str::slug($slug !== '' ? $slug : $name),
        ]);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique(StoreCategory::class, 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:800'],
            'active' => ['nullable', 'boolean'],
            'order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        return parent::validated() + ['active' => $this->boolean('active')];
    }
}
