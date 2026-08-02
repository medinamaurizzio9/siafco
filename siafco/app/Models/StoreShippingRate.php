<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreShippingRate extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    public const PUBLIC_UUID_COLUMN = 'public_code';

    public const SCOPE_NATIONAL = 'national';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_CITY = 'city';
    public const SCOPE_ZONE = 'zone';

    protected $fillable = [
        'scope',
        'department',
        'city',
        'zone',
        'amount',
        'currency',
        'active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
