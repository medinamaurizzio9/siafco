<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'whatsapp_number_encrypted',
        'whatsapp_number_hash',
        'whatsapp_number_hint',
        'whatsapp_enabled',
        'pickup_enabled',
        'shipping_enabled',
        'pickup_instructions',
        'shipping_instructions',
        'default_currency',
        'max_receipt_size_kb',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'whatsapp_number_encrypted',
        'whatsapp_number_hash',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_number_encrypted' => 'encrypted',
            'whatsapp_enabled' => 'boolean',
            'pickup_enabled' => 'boolean',
            'shipping_enabled' => 'boolean',
            'max_receipt_size_kb' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'whatsapp_enabled' => false,
            'pickup_enabled' => true,
            'shipping_enabled' => false,
            'default_currency' => 'BOB',
            'max_receipt_size_kb' => 6144,
        ]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
