<?php

namespace Tests\Feature;

use App\Models\InvestmentAdvisor;
use App\Models\InvestmentCrmSetting;
use App\Models\User;
use App\Services\InvestmentCrmSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvestmentCrmSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_and_update_crm_settings(): void
    {
        $admin = $this->internalUser('administrador');

        $this->actingAs($admin)->get(route('investments.crm.settings.edit'))
            ->assertOk()->assertSee('Configuración del CRM');

        $this->actingAs($admin)->put(route('investments.crm.settings.update'), [
            'public_form_title' => 'Una oportunidad para crecer',
            'public_form_submit_text' => 'Quiero conversar',
        ])->assertRedirect();

        $this->assertDatabaseHas('investment_crm_settings', [
            'singleton_key' => true,
            'public_form_title' => 'Una oportunidad para crecer',
        ]);
        $this->assertSame('Una oportunidad para crecer', app(InvestmentCrmSettingsService::class)->all()['public_form_title']);
    }

    public function test_manager_can_view_but_cannot_update_and_other_roles_cannot_access(): void
    {
        $manager = $this->internalUser('gerente');
        $cashier = $this->internalUser('caja');

        $this->actingAs($manager)->get(route('investments.crm.settings.edit'))->assertOk();
        $this->actingAs($manager)->put(route('investments.crm.settings.update'), ['public_form_title' => 'No permitido'])->assertForbidden();
        $this->actingAs($cashier)->get(route('investments.crm.settings.edit'))->assertForbidden();
    }

    public function test_public_form_uses_escaped_settings_and_keeps_real_advisor_data(): void
    {
        $advisor = $this->advisor();
        InvestmentCrmSetting::create([
            'singleton_key' => true,
            'public_form_title' => '<script>alert(1)</script> Inversión segura',
            'public_form_submit_text' => 'Solicitar información',
        ]);

        $this->get(route('investments.advisors.public', $advisor->public_token))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Inversión segura', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Solicitar información')
            ->assertSee($advisor->advisor_number)
            ->assertSee($advisor->full_name);
    }

    public function test_success_and_login_use_configured_texts_while_prospect_code_remains_dynamic(): void
    {
        $advisor = $this->advisor();
        InvestmentCrmSetting::create([
            'singleton_key' => true,
            'success_title' => 'Solicitud recibida',
            'login_title' => 'Portal comercial',
        ]);

        $this->withSession(['investment_prospect_result' => ['status' => 'created', 'prospect_number' => 'PRO-000321']])
            ->get(route('investments.advisors.public', $advisor->public_token))
            ->assertOk()->assertSee('Solicitud recibida')->assertSee('PRO-000321');

        $this->get(route('investment-crm.advisor.login'))->assertOk()->assertSee('Portal comercial');
    }

    public function test_defaults_are_available_and_restore_clears_overrides_without_deleting_singleton(): void
    {
        $admin = $this->internalUser('superadministrador');
        InvestmentCrmSetting::create(['singleton_key' => true, 'public_form_title' => 'Título temporal']);

        $this->actingAs($admin)->post(route('investments.crm.settings.restore'))->assertRedirect();

        $this->assertSame(1, InvestmentCrmSetting::count());
        $this->assertNull(InvestmentCrmSetting::firstOrFail()->public_form_title);
        $this->assertSame(app(InvestmentCrmSettingsService::class)->defaults()['public_form_title'], app(InvestmentCrmSettingsService::class)->all()['public_form_title']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'investment_crm_settings_defaults_restored']);
    }

    public function test_legacy_cached_settings_cannot_omit_phase_three_rating_defaults(): void
    {
        $advisor = $this->advisor();
        InvestmentCrmSetting::create(['singleton_key' => true, 'service_rating_question' => null]);
        Cache::put('investment_crm_settings', ['public_form_title' => 'Formulario anterior'], 3600);

        $this->get(route('investments.advisors.public', $advisor->public_token))
            ->assertOk()
            ->assertSee('¿Cómo califica la atención recibida por su asesor?')
            ->assertSee('Mala')
            ->assertSee('Regular')
            ->assertSee('Buena')
            ->assertSee('Muy buena');
    }

    public function test_custom_rating_texts_are_saved_and_used_by_public_form(): void
    {
        $advisor = $this->advisor();
        $admin = $this->internalUser('administrador');

        $this->actingAs($admin)->put(route('investments.crm.settings.update'), [
            'service_rating_question' => '¿Cómo fue tu atención?',
            'service_rating_very_good_label' => 'Excelente',
        ])->assertRedirect();

        $this->get(route('investments.advisors.public', $advisor->public_token))
            ->assertOk()->assertSee('¿Cómo fue tu atención?')->assertSee('Excelente');
    }

    public function test_restore_includes_all_phase_three_rating_texts(): void
    {
        $admin = $this->internalUser('superadministrador');
        InvestmentCrmSetting::create([
            'singleton_key' => true,
            'service_rating_question' => 'Pregunta temporal',
            'service_rating_very_good_label' => 'Excelente',
        ]);

        $this->actingAs($admin)->post(route('investments.crm.settings.restore'))->assertRedirect();
        $settings = app(InvestmentCrmSettingsService::class)->all();
        $this->assertSame('¿Cómo califica la atención recibida por su asesor?', $settings['service_rating_question']);
        $this->assertSame('Opcional', $settings['service_rating_optional_text']);
        $this->assertSame('Mala', $settings['service_rating_poor_label']);
        $this->assertSame('Regular', $settings['service_rating_regular_label']);
        $this->assertSame('Buena', $settings['service_rating_good_label']);
        $this->assertSame('Muy buena', $settings['service_rating_very_good_label']);
    }

    private function internalUser(string $role): User
    {
        return User::create(['name' => "Usuario {$role}", 'email' => "{$role}-crm-settings@test.local", 'role' => $role, 'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('secret')]);
    }

    private function advisor(): InvestmentAdvisor
    {
        return InvestmentAdvisor::create(['advisor_number' => 'ASE-0099', 'public_id' => fake()->uuid(), 'public_token' => hash('sha256', fake()->uuid()), 'full_name' => 'Andrea Mendoza', 'phone' => '71234567', 'is_active' => true]);
    }
}
