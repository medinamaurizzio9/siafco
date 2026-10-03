<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\InstitutionalSetting;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AffiliationReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_affiliation_report_and_unauthorized_user_cannot(): void
    {
        $admin = $this->internalUser('administrador');
        $affiliateUser = User::factory()->create(['role' => 'afiliado', 'user_type' => 'affiliate']);

        $this->actingAs($admin)->get(route('reports.affiliations.index'))->assertOk()
            ->assertSee('Reporte de Afiliaciones');

        $this->actingAs($affiliateUser)->get(route('reports.affiliations.index'))->assertForbidden();
    }

    public function test_report_lists_and_filters_affiliations_by_traceability_fields(): void
    {
        [$sector, $plan] = $this->catalog();
        $mauricio = $this->internalUser('administrador', ['name' => 'Mauricio Medina']);
        $other = $this->internalUser('secretaria', ['name' => 'Otra Administradora']);
        $approver = $this->internalUser('gerente', ['name' => 'Gerente Aprobador']);

        $web = $this->affiliate($sector, $plan, [
            'full_name' => 'JUAN WEB',
            'ci' => 'WEB-1',
            'institution' => 'COLEGIO CENTRAL',
            'origin' => 'web',
            'managed_by' => $mauricio->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'status' => 'activo',
        ]);
        PublicAffiliationRequest::create([
            'person_id' => $web->person_id,
            'affiliate_id' => $web->id,
            'user_id' => $web->user_id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'public_token' => fake()->uuid(),
            'request_code' => 'SOL-WEB',
            'amount_due' => 120,
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
            'reviewed_by' => $approver->id,
            'reviewed_at' => now(),
        ]);

        $manual = $this->affiliate($sector, $plan, [
            'full_name' => 'MARIA ADMIN',
            'ci' => 'ADM-1',
            'origin' => 'administrative',
            'registered_by' => $other->id,
            'managed_by' => $other->id,
            'status' => 'pendiente_pago',
        ]);

        $this->actingAs($mauricio)
            ->get(route('reports.affiliations.index', [
                'origin' => 'web',
                'managed_by' => $mauricio->id,
                'approved_by' => $approver->id,
                'name' => 'JUAN',
                'ci' => 'WEB',
                'organization' => 'COLEGIO',
                'sector_id' => $sector->id,
                'status' => 'activo',
            ]))
            ->assertOk()
            ->assertSee('JUAN WEB')
            ->assertSee('AUTOAFILIACIÓN WEB')
            ->assertSee('Mauricio Medina')
            ->assertSee('Gerente Aprobador')
            ->assertDontSee('MARIA ADMIN');

        $this->actingAs($mauricio)
            ->get(route('reports.affiliations.index', ['registered_by' => $other->id]))
            ->assertOk()
            ->assertSee('MARIA ADMIN')
            ->assertSee('Otra Administradora')
            ->assertDontSee('JUAN WEB');

        $this->assertNotSame($web->id, $manual->id);
    }

    public function test_report_pagination_keeps_filters(): void
    {
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('administrador');

        foreach (range(1, 30) as $index) {
            $this->affiliate($sector, $plan, [
                'full_name' => 'PAGINADO '.$index,
                'ci' => 'PAG-'.$index,
                'origin' => 'administrative',
                'registered_by' => $admin->id,
                'managed_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('reports.affiliations.index', ['origin' => 'administrative']))
            ->assertOk()
            ->assertSee('origin=administrative', false);
    }

    public function test_export_xlsx_respects_filters_and_requires_export_permission(): void
    {
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('administrador');
        $viewer = $this->internalUser('consulta');

        $this->affiliate($sector, $plan, ['full_name' => 'EXPORTAR SI', 'ci' => 'EXP-1', 'origin' => 'administrative', 'registered_by' => $admin->id]);
        $this->affiliate($sector, $plan, ['full_name' => 'EXPORTAR NO', 'ci' => 'EXP-2', 'origin' => 'web']);

        $this->actingAs($viewer)
            ->get(route('reports.affiliations.export'))
            ->assertForbidden();

        $response = $this->actingAs($admin)
            ->get(route('reports.affiliations.export', ['registered_by' => $admin->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $content = $response->getContent();
        $this->assertStringContainsString('EXPORTAR SI', $content);
        $this->assertStringNotContainsString('EXPORTAR NO', $content);
        $this->assertStringContainsString('Registrado por', $content);
    }

    public function test_web_affiliation_manager_setting_is_saved_and_audited(): void
    {
        $actor = $this->internalUser('administrador');
        $manager = $this->internalUser('gerente', ['name' => 'Nuevo Responsable Web']);
        $setting = InstitutionalSetting::current();

        $this->actingAs($actor)
            ->put(route('institutional-settings.update'), [
                'institution_name' => $setting->institution_name,
                'primary_color' => $setting->primary_color,
                'secondary_color' => $setting->secondary_color,
                'email' => $setting->email,
                'phone' => $setting->phone,
                'address' => $setting->address,
                'login_title' => $setting->login_title ?: 'SISTEMA DE AFILIACIÓN',
                'login_institution_name' => $setting->login_institution_name ?: 'COOPERATIVA TIERRA BENDITA',
                'login_affiliate_message' => $setting->loginAppearance()['affiliate_message'],
                'login_overlay_opacity' => $setting->login_overlay_opacity ?: 65,
                'web_affiliation_manager_id' => $manager->id,
            ])->assertSessionHasNoErrors();

        $this->assertSame($manager->id, InstitutionalSetting::current()->fresh()->web_affiliation_manager_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'web_affiliation_manager_updated']);
    }

    private function catalog(): array
    {
        $sector = Sector::create(['name' => 'Sindicato Salud', 'code' => 'SAL', 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'Plan Base',
            'type' => 'independiente',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'currency' => 'BOB',
            'is_active' => true,
        ]);

        return [$sector, $plan];
    }

    private function affiliate(Sector $sector, AffiliationPlan $plan, array $overrides = []): Affiliate
    {
        $person = Person::create([
            'full_name' => $overrides['full_name'] ?? 'AFILIADO REPORTE',
            'ci' => $overrides['ci'] ?? fake()->unique()->numerify('CI####'),
            'email' => fake()->unique()->safeEmail(),
        ]);
        $user = User::factory()->create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $person->email,
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'password' => Hash::make('Secret1234'),
        ]);

        return Affiliate::create(array_merge([
            'person_id' => $person->id,
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => $person->full_name,
            'ci' => $person->ci,
            'email' => $person->email,
            'institution' => 'INSTITUCIÓN BASE',
            'registration_number' => fake()->unique()->bothify('SAL-######'),
            'status' => 'pendiente_pago',
            'verification_token' => fake()->uuid(),
        ], $overrides));
    }

    private function internalUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
        ], $overrides));
    }
}
