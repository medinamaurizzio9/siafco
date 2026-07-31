<?php

namespace App\Http\Resources\Api\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('affiliate.sector', 'affiliate.plan');

        return [
            'user' => new UserResource($this->resource),
            'affiliate' => $this->resource->affiliate
                ? new AffiliateResource($this->resource->affiliate)
                : null,
            'allowed_profile_fields' => [
                'phone',
                'email',
                'address',
                'birth_date',
                'marital_status',
                'photo',
            ],
        ];
    }
}
