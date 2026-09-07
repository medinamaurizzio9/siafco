<?php

namespace App\Services;

use App\Models\InvestmentCrmSetting;
use Illuminate\Support\Facades\Cache;

class InvestmentCrmSettingsService
{
    public const FIELDS = ['service_rating_question', 'service_rating_optional_text', 'service_rating_poor_label', 'service_rating_regular_label', 'service_rating_good_label', 'service_rating_very_good_label', 'public_form_brand', 'public_form_subtitle', 'public_form_title', 'public_form_description', 'public_form_name_label', 'public_form_name_placeholder', 'public_form_phone_label', 'public_form_phone_placeholder', 'public_form_shares_label', 'public_form_shares_placeholder', 'public_form_shares_help', 'public_form_contact_question', 'public_form_whatsapp_label', 'public_form_call_label', 'public_form_consent_text', 'public_form_submit_text', 'success_title', 'success_message', 'success_contact_message', 'success_code_label', 'advisor_portal_title', 'advisor_portal_subtitle', 'advisor_dashboard_greeting', 'advisor_dashboard_description', 'login_title', 'login_subtitle', 'login_code_label', 'login_pin_label', 'login_button_text', 'footer_text'];

    public function defaults(): array
    {
        return [
            'service_rating_question' => '¿Cómo califica la atención recibida por su asesor?', 'service_rating_optional_text' => 'Opcional',
            'service_rating_poor_label' => 'Mala', 'service_rating_regular_label' => 'Regular', 'service_rating_good_label' => 'Buena', 'service_rating_very_good_label' => 'Muy buena',
            'public_form_brand' => 'SIAFCO', 'public_form_subtitle' => 'INVERSIONES', 'public_form_title' => 'Quiero información para invertir',
            'public_form_description' => 'Completa tus datos y recibe información personalizada sobre nuestras oportunidades de inversión.',
            'public_form_name_label' => 'Nombre completo', 'public_form_name_placeholder' => 'Ej. Juan Pérez García',
            'public_form_phone_label' => 'Número de celular', 'public_form_phone_placeholder' => 'Ej. 71234567',
            'public_form_shares_label' => 'Cantidad de acciones', 'public_form_shares_placeholder' => 'Ej. 10, 20, 50...',
            'public_form_shares_help' => 'Cantidad aproximada sobre la que desea recibir información.',
            'public_form_contact_question' => '¿Cómo desea que lo contactemos?', 'public_form_whatsapp_label' => 'WhatsApp', 'public_form_call_label' => 'Llamada',
            'public_form_consent_text' => 'Al registrarte autorizas que un asesor de inversiones se comunique contigo.', 'public_form_submit_text' => 'ENVIAR INFORMACIÓN',
            'success_title' => 'REGISTRO REALIZADO', 'success_message' => 'Gracias. Tu solicitud de información fue registrada correctamente.',
            'success_contact_message' => 'Un asesor se pondrá en contacto contigo.', 'success_code_label' => 'Código de registro',
            'advisor_portal_title' => 'CRM DE INVERSIONES', 'advisor_portal_subtitle' => 'SIAFCO', 'advisor_dashboard_greeting' => 'Hola',
            'advisor_dashboard_description' => 'Aquí tienes un resumen de tu actividad y tus prospectos.', 'login_title' => 'CRM DE INVERSIONES',
            'login_subtitle' => 'Acceso para asesores', 'login_code_label' => 'Código CRM', 'login_pin_label' => 'PIN', 'login_button_text' => 'INGRESAR',
            'footer_text' => 'SIAFCO · CRM de Inversiones',
        ];
    }

    public function all(): array
    {
        $stored = Cache::remember('investment_crm_settings', 3600, function () {
            $row = InvestmentCrmSetting::where('singleton_key', true)->first();

            return collect(self::FIELDS)
                ->mapWithKeys(fn (string $key) => [$key => $row?->{$key}])
                ->filter(fn ($value) => filled($value))
                ->all();
        });

        // Always compose after reading cache so entries created by an older release
        // cannot omit newly introduced setting keys.
        $overrides = collect($stored)
            ->only(self::FIELDS)
            ->filter(fn ($value) => filled($value))
            ->all();

        return array_replace($this->defaults(), $overrides);
    }

    public function update(array $data): void
    {
        $row = InvestmentCrmSetting::firstOrCreate(['singleton_key' => true]);
        $changed = [];
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $data)) continue;
            $value = filled($data[$field] ?? null) ? trim($data[$field]) : null;
            if ($row->{$field} !== $value) $changed[] = $field;
            $row->{$field} = $value;
        }
        $row->save();
        Cache::forget('investment_crm_settings');
        AuditService::record('investment_crm_settings_updated', $row, ['changed_fields' => $changed]);
    }

    public function restore(): void
    {
        $row = InvestmentCrmSetting::firstOrCreate(['singleton_key' => true]);
        $row->fill(array_fill_keys(self::FIELDS, null))->save();
        Cache::forget('investment_crm_settings');
        AuditService::record('investment_crm_settings_defaults_restored', $row, ['changed_fields' => self::FIELDS]);
    }
}
