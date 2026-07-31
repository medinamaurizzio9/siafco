<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileApiIdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'scope',
        'idempotency_key',
        'request_hash',
        'status',
        'response_status',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
        ];
    }
}
