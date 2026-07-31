<?php

namespace App\Http\Resources\Api\Mobile\V1;

use App\Support\AffiliationStatusPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AffiliateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'full_name' => $this->full_name,
            'ci' => $this->ci,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'birth_date' => $this->birth_date?->toDateString(),
            'marital_status' => $this->marital_status,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'registration_number' => $this->registration_number,
            'status' => $this->status,
            'status_label' => AffiliationStatusPresenter::label($this->status),
            'status_description' => AffiliationStatusPresenter::description($this->status),
            'access_level' => $this->status === 'activo' ? 'full' : 'limited',
            'sector' => $this->whenLoaded('sector', fn () => [
                'name' => $this->sector?->name,
                'code' => $this->sector?->code,
                'regional' => $this->sector?->regional,
                'institution' => $this->sector?->institution,
            ]),
            'plan' => $this->whenLoaded('plan', fn () => [
                'name' => $this->plan?->name,
                'type' => $this->plan?->type,
                'currency' => $this->plan?->currency,
                'affiliation_fee' => $this->plan?->affiliation_fee !== null ? (float) $this->plan->affiliation_fee : null,
                'credential_fee' => $this->plan?->credential_fee !== null ? (float) $this->plan->credential_fee : null,
                'total_amount' => $this->plan ? $this->plan->total_amount : null,
            ]),
        ];
    }
}
