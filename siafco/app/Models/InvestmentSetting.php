<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class InvestmentSetting extends Model
{
    protected $fillable = [
        'company_name',
        'company_legal_name',
        'nit',
        'address',
        'phone',
        'email',
        'receipt_logo',
        'currency',
        'share_unit_price',
        'minimum_shares',
        'maximum_shares',
        'monthly_return_percentage',
        'waiting_months',
        'contract_years',
        'reservation_days',
        'maximum_shares_per_person',
        'renewal_enabled',
        'production_bonus_enabled',
        'extra_amount_enabled',
        'receipt_prefix',
        'next_receipt_number',
        'receipt_legal_text',
        'alert_days_before_maturity',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'share_unit_price' => 'decimal:2',
            'monthly_return_percentage' => 'decimal:2',
            'maximum_shares_per_person' => 'boolean',
            'renewal_enabled' => 'boolean',
            'production_bonus_enabled' => 'boolean',
            'extra_amount_enabled' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return Cache::remember('investment_settings.current', 300, fn () => self::where('active', true)->latest('id')->first()
            ?? self::query()->create([
                'company_name' => 'Cooperativa Tierra Bendita',
                'currency' => 'BOB',
            ]));
    }

    public static function clearCurrentCache(): void
    {
        Cache::forget('investment_settings.current');
    }

    public function logoUrl(): ?string
    {
        return $this->receipt_logo ? Storage::disk('public')->url($this->receipt_logo) : null;
    }
}
