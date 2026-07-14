<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class InstitutionalSetting extends Model
{
    private static ?self $currentInstance = null;

    protected $fillable = [
        'institution_name',
        'logo_path',
        'primary_color',
        'secondary_color',
        'email',
        'phone',
        'address',
        'payment_qr_path',
    ];

    public static function current(): self
    {
        if (self::$currentInstance) {
            return self::$currentInstance;
        }

        self::$currentInstance = Cache::rememberForever('institutional_settings.current', function () {
            return static::firstOrCreate([], [
                'institution_name' => 'Cooperativa Tierra Bendita',
                'primary_color' => '#0b1f3a',
                'secondary_color' => '#d4af37',
                'email' => 'no-reply@siafco.test',
            ]);
        });

        return self::$currentInstance;
    }

    public static function fallback(): self
    {
        return new self([
            'institution_name' => 'Cooperativa Tierra Bendita',
            'primary_color' => '#0b1f3a',
            'secondary_color' => '#d4af37',
            'payment_qr_path' => 'institutional/payment-qr.png',
            'email' => 'no-reply@siafco.test',
        ]);
    }

    public static function clearCurrentCache(): void
    {
        self::$currentInstance = null;
        Cache::forget('institutional_settings.current');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function logoAbsolutePath(): ?string
    {
        return $this->logo_path ? storage_path('app/public/'.$this->logo_path) : null;
    }

    public function paymentQrPath(): string
    {
        return $this->payment_qr_path ?: 'institutional/payment-qr.png';
    }

    public function paymentQrUrl(): string
    {
        return Storage::url($this->paymentQrPath());
    }

    public function paymentQrAbsolutePath(): string
    {
        return storage_path('app/public/'.$this->paymentQrPath());
    }
}
