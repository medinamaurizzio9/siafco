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
        'payment_bank',
        'payment_holder',
        'payment_account',
        'payment_instructions',
        'login_background_path',
        'login_logo_path',
        'login_title',
        'login_institution_name',
        'login_affiliate_message',
        'login_overlay_opacity',
    ];

    protected function casts(): array
    {
        return ['login_overlay_opacity' => 'integer'];
    }

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

    public function loginBackgroundUrl(): string
    {
        if ($this->login_background_path && Storage::disk('public')->exists($this->login_background_path)) {
            return Storage::url($this->login_background_path);
        }

        return asset('images/login/default-login-background.webp');
    }

    public function loginLogoUrl(): ?string
    {
        $path = $this->login_logo_path ?: $this->logo_path;

        return $path && Storage::disk('public')->exists($path) ? Storage::url($path) : null;
    }

    public function loginAppearance(): array
    {
        $defaultMessage = 'Bienvenido a nuestra plataforma institucional. Ingresa para consultar tu afiliación, descargar tu credencial y acceder a los servicios y beneficios disponibles para nuestros afiliados.';

        return [
            'background_url' => $this->loginBackgroundUrl(),
            'logo_url' => $this->loginLogoUrl(),
            'title' => $this->login_title ?: 'SISTEMA DE AFILIACIÓN',
            'institution_name' => $this->login_institution_name ?: 'COOPERATIVA TIERRA BENDITA',
            'affiliate_message' => $this->login_affiliate_message ?: $defaultMessage,
            'overlay_opacity' => min(0.9, max(0.2, ((int) ($this->login_overlay_opacity ?: 65)) / 100)),
        ];
    }
}
