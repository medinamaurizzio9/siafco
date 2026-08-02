<?php

namespace App\Http\Resources\Api\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'user_type' => $this->user_type,
            'must_change_password' => (bool) $this->must_change_password,
            'is_active' => (bool) $this->is_active,
            'last_login_at' => $this->last_login_at?->toISOString(),
        ];
    }
}
