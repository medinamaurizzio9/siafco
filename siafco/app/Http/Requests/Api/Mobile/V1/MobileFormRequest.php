<?php

namespace App\Http\Requests\Api\Mobile\V1;

use App\Http\Responses\MobileApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class MobileFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            MobileApiResponse::error('Los datos enviados no son válidos.', 422, $validator->errors()->toArray())
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            MobileApiResponse::error('No tienes autorización para realizar esta acción.', 403)
        );
    }
}
