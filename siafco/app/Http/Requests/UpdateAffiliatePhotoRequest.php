<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAffiliatePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('affiliates.manage_photo') ?? false;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
        ];
    }
}
