<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliateSupportAttributionService;
use App\Support\AffiliateSupportChannel;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AffiliateSupportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_affiliate_is_attributed_to_real_registrar(): void
    {
        $registrar = $this->internalUser('cajero', 'María López');
        $affiliate = $this->affiliate('OFICINA UNO');
        $this->payment($affiliate, ['source' => 'office_cash', 'registered_by' => $registrar->id]);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($affiliate->fresh());

        $this->assertSame($registrar->id, $resolved['responsible_user']->id);
        $this->assertSame('internal_registration', $resolved['attribution_type']);
        $this->assertSame(AffiliateSupportChannel::OFFICE, $resolved['channel']);
    }

    public function test_web_and_express_affiliates_are_attributed_to_configured_support_user(): void
    {
        $mauricio = $this->internalUser('administrador', 'Maurizzio Medina');
        config(['affiliation.web_support_user_id' => $mauricio->id]);

        $web = $this->publicAffiliate('WEB UNO', false);
        $express = $this->publicAffiliate('EXPRESS UNO', true);
        $resolver = app(AffiliateSupportAttributionService::class);

        $webResolved = $resolver->resolve($web->fresh());
        $expressResolved = $resolver->resolve($express->fresh());

        $this->assertSame($mauricio->id, $webResolved['responsible_user']->id);
        $this->assertSame(AffiliateSupportChannel::WEB, $webResolved['channel']);
        $this->assertSame($mauricio->id, $expressResolved['responsible_user']->id);
        $this->assertSame(AffiliateSupportChannel::EXPRESS, $expressResolved['channel']);
    }

    public function test_web_affiliate_without_config_uses_oldest_active_administrator(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $oldest = $this->internalUser('administrador', 'Admin Antiguo', ['created_at' => '2026-01-01 08:00:00']);
        $this->internalUser('superadministrador', 'Admin Nuevo', ['created_at' => '2026-01-02 08:00:00']);
        $web = $this->publicAffiliate('WEB FALLBACK', false);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($web->fresh());

        $this->assertSame($oldest->id, $resolved['responsible_user']->id);
        $this->assertSame(AffiliateSupportChannel::WEB, $resolved['channel']);
    }

    public function test_express_affiliate_without_config_uses_oldest_active_administrator(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $oldest = $this->internalUser('superadministrador', 'Super Antiguo', ['created_at' => '2026-01-01 08:00:00']);
        $this->internalUser('administrador', 'Admin Nuevo', ['created_at' => '2026-01-02 08:00:00']);
        $express = $this->publicAffiliate('EXPRESS FALLBACK', true);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($express->fresh());

        $this->assertSame($oldest->id, $resolved['responsible_user']->id);
        $this->assertSame(AffiliateSupportChannel::EXPRESS, $resolved['channel']);
    }

    public function test_inactive_administrator_cannot_be_fallback(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $inactive = $this->internalUser('administrador', 'Admin Inactivo', [
            'created_at' => '2026-01-01 08:00:00',
            'is_active' => false,
        ]);
        $active = $this->internalUser('administrador', 'Admin Activo', ['created_at' => '2026-01-02 08:00:00']);
        $web = $this->publicAffiliate('WEB ADMIN ACTIVO', false);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($web->fresh());

        $this->assertNotSame($inactive->id, $resolved['responsible_user']->id);
        $this->assertSame($active->id, $resolved['responsible_user']->id);
    }

    public function test_fallback_orders_administrators_by_created_at_then_id(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $sameDateFirst = $this->internalUser('administrador', 'Admin Uno', ['created_at' => '2026-01-01 08:00:00']);
        $this->internalUser('superadministrador', 'Admin Dos', ['created_at' => '2026-01-01 08:00:00']);
        $this->internalUser('administrador', 'Admin Tres', ['created_at' => '2026-01-02 08:00:00']);
        $express = $this->publicAffiliate('EXPRESS ORDEN', true);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($express->fresh());

        $this->assertSame($sameDateFirst->id, $resolved['responsible_user']->id);
    }

    public function test_public_affiliate_without_active_administrator_shows_unconfigured_responsible(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $admin = $this->internalUser('administrador', 'Admin Inactivo', ['is_active' => false]);
        $web = $this->publicAffiliate('WEB SIN ADMIN', false);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($web->fresh());

        $this->assertNull($resolved['responsible_user']);
        $this->actingAs($admin)
            ->get(route('reports.affiliate-support.index'))
            ->assertOk()
            ->assertSee('Sin responsable configurado');
    }

    public function test_reviewer_and_confirmor_do_not_replace_capture_responsible(): void
    {
        $mauricio = $this->internalUser('administrador', 'Maurizzio Medina');
        $reviewer = $this->internalUser('gerente', 'Revisor Real');
        $confirmor = $this->internalUser('gerente', 'Confirmador Real');
        config(['affiliation.web_support_user_id' => $mauricio->id]);

        $affiliate = $this->publicAffiliate('WEB REVISADA', true, [
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => '2026-09-24 15:00:00',
        ]);
        $affiliate->latestPayment->update([
            'confirmed_by' => $confirmor->id,
            'confirmed_at' => '2026-09-24 16:00:00',
            'status' => PaymentStatus::CONFIRMED,
        ]);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($affiliate->fresh());

        $this->assertSame($mauricio->id, $resolved['responsible_user']->id);
        $this->assertNotSame($reviewer->id, $resolved['responsible_user']->id);
        $this->assertNotSame($confirmor->id, $resolved['responsible_user']->id);
    }

    public function test_reviewer_does_not_replace_fallback_responsible(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $fallback = $this->internalUser('administrador', 'Admin Fallback', ['created_at' => '2026-01-01 08:00:00']);
        $reviewer = $this->internalUser('gerente', 'Revisor Real', ['created_at' => '2026-01-02 08:00:00']);
        $affiliate = $this->publicAffiliate('WEB REVISOR NO CUENTA', false, [
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => '2026-09-24 15:00:00',
        ]);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($affiliate->fresh());

        $this->assertSame($fallback->id, $resolved['responsible_user']->id);
        $this->assertNotSame($reviewer->id, $resolved['responsible_user']->id);
    }

    public function test_confirmor_does_not_replace_fallback_responsible(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $fallback = $this->internalUser('administrador', 'Admin Fallback', ['created_at' => '2026-01-01 08:00:00']);
        $confirmor = $this->internalUser('gerente', 'Confirmador Real', ['created_at' => '2026-01-02 08:00:00']);
        $affiliate = $this->publicAffiliate('EXPRESS CONFIRMADOR NO CUENTA', true);
        $affiliate->latestPayment->update([
            'confirmed_by' => $confirmor->id,
            'confirmed_at' => '2026-09-24 16:00:00',
            'status' => PaymentStatus::CONFIRMED,
        ]);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($affiliate->fresh());

        $this->assertSame($fallback->id, $resolved['responsible_user']->id);
        $this->assertNotSame($confirmor->id, $resolved['responsible_user']->id);
    }

    public function test_ambiguous_origin_and_missing_configuration_do_not_fail(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $admin = $this->internalUser('administrador');
        $unknown = $this->affiliate('SIN ORIGEN');
        $web = $this->publicAffiliate('WEB SIN CONFIG', false);

        $this->actingAs($admin)
            ->get(route('reports.affiliate-support.index'))
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee('DESCONOCIDO');

        $resolvedUnknown = app(AffiliateSupportAttributionService::class)->resolve($unknown->fresh());
        $resolvedWeb = app(AffiliateSupportAttributionService::class)->resolve($web->fresh());

        $this->assertSame('unknown', $resolvedUnknown['attribution_type']);
        $this->assertSame($admin->id, $resolvedWeb['responsible_user']->id);
        $this->assertSame('web_assigned', $resolvedWeb['attribution_type']);
    }

    public function test_office_affiliate_keeps_real_user_and_never_uses_fallback(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $fallback = $this->internalUser('administrador', 'Admin Fallback', ['created_at' => '2026-01-01 08:00:00']);
        $registrar = $this->internalUser('cajero', 'Caja Real', ['created_at' => '2026-01-02 08:00:00']);
        $affiliate = $this->affiliate('OFICINA NO FALLBACK');
        $this->payment($affiliate, ['source' => 'office_qr', 'registered_by' => $registrar->id]);

        $resolved = app(AffiliateSupportAttributionService::class)->resolve($affiliate->fresh());

        $this->assertSame($registrar->id, $resolved['responsible_user']->id);
        $this->assertNotSame($fallback->id, $resolved['responsible_user']->id);
    }

    public function test_filters_by_responsible_channel_date_sector_and_payment_status(): void
    {
        $mauricio = $this->internalUser('administrador', 'Maurizzio Medina');
        $registrar = $this->internalUser('cajero', 'Caja Uno');
        config(['affiliation.web_support_user_id' => $mauricio->id]);
        $sectorA = Sector::create(['name' => 'Sector A', 'code' => 'SA', 'is_active' => true]);
        $sectorB = Sector::create(['name' => 'Sector B', 'code' => 'SB', 'is_active' => true]);
        $planA = $this->plan($sectorA);
        $planB = $this->plan($sectorB);

        $web = $this->publicAffiliate('WEB FILTRADO', false, [], $sectorA, $planA, '2026-09-24 02:30:00');
        $office = $this->affiliate('OFICINA FILTRADA', $sectorB, $planB, ['created_at' => '2026-09-24 14:00:00']);
        $this->payment($office, ['source' => 'office_cash', 'registered_by' => $registrar->id, 'status' => PaymentStatus::UNDER_REVIEW]);

        $this->actingAs($this->internalUser('administrador'))
            ->get(route('reports.affiliate-support.index', ['responsible_user_id' => $mauricio->id]))
            ->assertOk()
            ->assertSee($web->full_name)
            ->assertDontSee($office->full_name);

        $this->actingAs($this->internalUser('administrador'))
            ->get(route('reports.affiliate-support.index', ['channel' => AffiliateSupportChannel::OFFICE]))
            ->assertOk()
            ->assertSee($office->full_name)
            ->assertDontSee($web->full_name);

        $this->actingAs($this->internalUser('administrador'))
            ->get(route('reports.affiliate-support.index', ['from' => '2026-09-23', 'to' => '2026-09-23']))
            ->assertOk()
            ->assertSee($web->full_name)
            ->assertDontSee($office->full_name);

        $this->actingAs($this->internalUser('administrador'))
            ->get(route('reports.affiliate-support.index', ['sector_id' => $sectorB->id, 'payment_status' => PaymentStatus::UNDER_REVIEW]))
            ->assertOk()
            ->assertSee($office->full_name)
            ->assertDontSee($web->full_name);
    }

    public function test_summary_counts_and_csv_match_filters_without_sensitive_data(): void
    {
        $mauricio = $this->internalUser('administrador', 'Maurizzio Medina');
        $registrar = $this->internalUser('cajero', 'Caja Dos');
        config(['affiliation.web_support_user_id' => $mauricio->id]);
        $web = $this->publicAffiliate('WEB CSV', true);
        $office = $this->affiliate('OFICINA CSV');
        $this->payment($office, ['source' => 'manual_admin', 'registered_by' => $registrar->id, 'status' => PaymentStatus::CONFIRMED]);

        $this->actingAs($this->internalUser('administrador'))
            ->get(route('reports.affiliate-support.index'))
            ->assertOk()
            ->assertSee('Web / Express')
            ->assertSee('Oficina / Internos')
            ->assertSee('Maurizzio Medina')
            ->assertSee('Caja Dos');

        $csv = $this->actingAs($this->internalUser('administrador'))
            ->get(route('reports.affiliate-support.csv', ['channel' => AffiliateSupportChannel::EXPRESS]));

        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString($web->full_name, $content);
        $this->assertStringNotContainsString($office->full_name, $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('verification_token', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('user_agent', $content);
    }

    public function test_report_summary_counts_fallback_responsible(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $fallback = $this->internalUser('administrador', 'Admin Resumen', ['created_at' => '2026-01-01 08:00:00']);
        $web = $this->publicAffiliate('WEB RESUMEN FALLBACK', false);
        $express = $this->publicAffiliate('EXPRESS RESUMEN FALLBACK', true);

        $this->actingAs($fallback)
            ->get(route('reports.affiliate-support.index'))
            ->assertOk()
            ->assertSee('Responsables activos')
            ->assertSee('Admin Resumen')
            ->assertSee($web->full_name)
            ->assertSee($express->full_name);
    }

    public function test_responsible_filter_includes_fallback_public_affiliates(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $fallback = $this->internalUser('administrador', 'Admin Filtro', ['created_at' => '2026-01-01 08:00:00']);
        $other = $this->internalUser('administrador', 'Admin Otro', ['created_at' => '2026-01-02 08:00:00']);
        $sector = Sector::create(['name' => 'Sector Filtro', 'code' => 'SF', 'is_active' => true]);
        $web = $this->publicAffiliate('WEB FILTRO FALLBACK', false, [], $sector, $this->plan($sector));

        $this->actingAs($fallback)
            ->get(route('reports.affiliate-support.index', ['responsible_user_id' => $fallback->id]))
            ->assertOk()
            ->assertSee($web->full_name);

        $this->actingAs($fallback)
            ->get(route('reports.affiliate-support.index', ['responsible_user_id' => $other->id]))
            ->assertOk()
            ->assertDontSee($web->full_name);
    }

    public function test_csv_outputs_fallback_responsible(): void
    {
        config(['affiliation.web_support_user_id' => null]);
        $fallback = $this->internalUser('administrador', 'Admin CSV Fallback', ['created_at' => '2026-01-01 08:00:00']);
        $web = $this->publicAffiliate('WEB CSV FALLBACK', false);

        $csv = $this->actingAs($fallback)->get(route('reports.affiliate-support.csv'));

        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString($web->full_name, $content);
        $this->assertStringContainsString('Admin CSV Fallback', $content);
        $this->assertStringContainsString('Administrador', $content);
    }

    public function test_permissions_and_report_do_not_modify_historical_records(): void
    {
        $admin = $this->internalUser('administrador');
        $unauthorized = $this->internalUser('consulta');
        $affiliate = $this->publicAffiliate('NO MUTAR', true);
        $payment = $affiliate->latestPayment;
        $application = $affiliate->publicRequest;
        $affiliateBefore = $affiliate->fresh()->getAttributes();
        $paymentBefore = $payment->fresh()->getAttributes();
        $applicationBefore = $application->fresh()->getAttributes();

        $this->actingAs($unauthorized)
            ->get(route('reports.affiliate-support.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('reports.affiliate-support.index'))
            ->assertOk();

        $this->assertSame($affiliateBefore, $affiliate->fresh()->getAttributes());
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertSame($applicationBefore, $application->fresh()->getAttributes());
    }

    private function publicAffiliate(string $name, bool $express, array $requestOverrides = [], ?Sector $sector = null, ?AffiliationPlan $plan = null, string $submittedAt = '2026-09-24 14:00:00'): Affiliate
    {
        $affiliate = $this->affiliate($name, $sector, $plan, ['created_at' => $submittedAt]);
        $paymentSubmittedAt = $express ? $submittedAt : '2026-09-25 15:00:00';

        $application = PublicAffiliationRequest::create(array_merge([
            'person_id' => $affiliate->person_id,
            'affiliate_id' => $affiliate->id,
            'user_id' => $affiliate->user_id,
            'sector_id' => $affiliate->sector_id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'public_token' => 'token-'.str()->uuid(),
            'request_code' => 'SOL-'.strtoupper(substr(md5($name), 0, 10)),
            'amount_due' => 120,
            'status' => $express ? 'payment_submitted' : 'pending_payment',
            'submitted_at' => $submittedAt,
            'payment_submitted_at' => $paymentSubmittedAt,
        ], $requestOverrides));

        $payment = $this->payment($affiliate, [
            'public_affiliation_request_id' => $application->id,
            'source' => 'web',
            'status' => $express ? PaymentStatus::UNDER_REVIEW : PaymentStatus::PENDING,
            'submitted_at' => $paymentSubmittedAt,
        ]);
        $affiliate->setRelation('latestPayment', $payment);
        $affiliate->setRelation('publicRequest', $application);

        return $affiliate->fresh('publicRequest', 'latestPayment');
    }

    private function affiliate(string $name, ?Sector $sector = null, ?AffiliationPlan $plan = null, array $overrides = []): Affiliate
    {
        $sector ??= Sector::create(['name' => 'Sector '.$name, 'code' => strtoupper(substr(md5($name), 0, 5)), 'is_active' => true]);
        $plan ??= $this->plan($sector);
        $person = Person::create(['full_name' => $name, 'ci' => 'CI'.strtoupper(substr(md5($name.microtime()), 0, 8))]);
        $user = User::factory()->create(['role' => 'afiliado', 'user_type' => 'affiliate', 'person_id' => $person->id]);

        return Affiliate::create(array_merge([
            'person_id' => $person->id,
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => $name,
            'ci' => $person->ci,
            'phone' => '70000000',
            'email' => strtolower(str_replace(' ', '.', $name)).'@test.local',
            'registration_number' => 'REG-'.strtoupper(substr(md5($name), 0, 8)),
            'status' => 'activo',
            'verification_token' => 'token-'.strtolower(substr(md5($name), 0, 12)),
        ], $overrides));
    }

    private function payment(Affiliate $affiliate, array $overrides = []): AffiliationPayment
    {
        return AffiliationPayment::create(array_merge([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'expected_amount' => 120,
            'paid_amount' => 120,
            'status' => PaymentStatus::CONFIRMED,
            'source' => 'office_cash',
            'payment_method' => 'efectivo',
            'payment_date' => '2026-09-24',
            'paid_at' => '2026-09-24 14:00:00',
            'submitted_at' => '2026-09-24 14:00:00',
        ], $overrides));
    }

    private function plan(Sector $sector): AffiliationPlan
    {
        return AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'Plan '.$sector->code,
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'is_active' => true,
        ]);
    }

    private function internalUser(string $role, string $name = 'Usuario Interno', array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $name.' '.$role.' '.str()->random(5),
            'email' => str()->uuid().'@test.local',
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('secret123'),
        ], $attributes));
    }
}
