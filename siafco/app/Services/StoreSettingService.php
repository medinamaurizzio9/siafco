<?php

namespace App\Services;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreSettingService
{
    public function __construct(private StoreWhatsAppNumberService $whatsApp) {}

    public function currentLocked(): StoreSetting
    {
        return DB::transaction(function () {
            $setting = StoreSetting::query()->lockForUpdate()->first();
            if ($setting) {
                return $setting;
            }

            return StoreSetting::create([
                'whatsapp_enabled' => false,
                'pickup_enabled' => true,
                'shipping_enabled' => false,
                'default_currency' => 'BOB',
                'max_receipt_size_kb' => 6144,
            ]);
        });
    }

    public function update(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $setting = StoreSetting::query()->lockForUpdate()->first() ?: new StoreSetting([
                'default_currency' => 'BOB',
                'max_receipt_size_kb' => 6144,
            ]);

            $oldHint = $setting->whatsapp_number_hint;
            $removeNumber = (bool) ($data['remove_whatsapp_number'] ?? false);
            $newNumber = trim((string) ($data['whatsapp_number'] ?? ''));

            if ($removeNumber) {
                $data['whatsapp_number_encrypted'] = null;
                $data['whatsapp_number_hash'] = null;
                $data['whatsapp_number_hint'] = null;
            } elseif ($newNumber !== '') {
                $normalized = $this->whatsApp->normalize($newNumber);
                $data['whatsapp_number_encrypted'] = $normalized;
                $data['whatsapp_number_hash'] = hash('sha256', $normalized);
                $data['whatsapp_number_hint'] = $this->whatsApp->hint($normalized);
            }

            unset($data['whatsapp_number'], $data['remove_whatsapp_number']);
            $data['updated_by'] = $actor->id;
            if (! $setting->exists) {
                $data['created_by'] = $actor->id;
            }

            $setting->fill($data)->save();

            return [
                'setting' => $setting->refresh(),
                'whatsapp_changed' => $oldHint !== $setting->whatsapp_number_hint,
                'old_hint' => $oldHint,
                'new_hint' => $setting->whatsapp_number_hint,
            ];
        });
    }
}
