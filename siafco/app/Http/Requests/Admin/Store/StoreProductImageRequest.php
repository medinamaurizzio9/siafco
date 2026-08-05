<?php

namespace App\Http\Requests\Admin\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage-products') === true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt' => ['nullable', 'string', 'max:180'],
            'is_primary' => ['nullable', 'boolean'],
            'order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');
                if ($product->images()->count() >= 8) {
                    $validator->errors()->add('image', 'El producto alcanzó el máximo de 8 imágenes.');
                }

                $file = $this->file('image');
                if (! $file || $validator->errors()->has('image')) {
                    return;
                }

                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $validator->errors()->add('image', 'La imagen no tiene un formato válido.');
                }
            },
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $data['is_primary'] = $this->boolean('is_primary');

        if ($key !== null) {
            return data_get($data, $key, $default);
        }

        return $data;
    }
}
