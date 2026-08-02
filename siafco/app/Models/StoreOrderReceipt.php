<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class StoreOrderReceipt extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'store_order_id',
        'uploaded_by_user_id',
        'path',
        'mime',
        'size_bytes',
        'sha256',
        'status',
        'submitted_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $hidden = ['path', 'sha256'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
