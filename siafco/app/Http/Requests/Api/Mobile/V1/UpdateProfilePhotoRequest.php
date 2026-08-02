<?php

namespace App\Http\Requests\Api\Mobile\V1;

class UpdateProfilePhotoRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'affiliate'
            && $this->user()?->role === 'afiliado'
            && $this->user()?->affiliate !== null;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=300,min_height=300'],
        ];
    }
}
