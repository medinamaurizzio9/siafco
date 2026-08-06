<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\DigitalCredential;
use App\Models\Person;
use App\Models\Sector;
use App\Models\StoreOrder;
use App\Models\User;
use App\Support\StoreDeliveryMethod;
use App\Support\StoreOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_operational_layout_real_metrics_and_readable_activity(): void
    {
        Cache::flush();

        $admin = User::factory()->create([
            'name' => 'SUPER ADMIN TEST',
            'role' => 'superadministrador',
            'user_type' => 'internal',
            'is_active' => true,
            'last_login_at' => now(),
        ]);
        $affiliate = $this->affiliate();

        AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'amount' => 120,
            'paid_amount' => 120,
            'currency' => 'BOB',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        DigitalCredential::create([
            'affiliate_id' => $affiliate->id,
            'qr_path' => 'credentials/qr/test.png',
            'generated_at' => now(),
        ]);

        StoreOrder::create([
            'affiliate_id' => $affiliate->id,
            'status' => StoreOrderStatus::PAYMENT_REVIEW,
            'delivery_method' => StoreDeliveryMethod::PICKUP,
            'subtotal' => 80,
            'discount_total' => 0,
            'shipping_total' => 0,
            'total' => 80,
            'currency' => 'BOB',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'payment_confirmed',
            'auditable_type' => AffiliationPayment::class,
            'auditable_id' => 1,
            'metadata' => ['receipt_number' => 'REC-001'],
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'mobile_login',
            'metadata' => ['hidden' => true],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Centro de operaciones')
            ->assertSee('dashboard-shell', false)
            ->assertSee('dashboard-kpis', false)
            ->assertSee('dashboard-chart-grid', false)
            ->assertSee('Afiliados activos')
            ->assertSee('Recaudacion')
            ->assertSee('Credenciales')
            ->assertSee('Pedidos tienda')
            ->assertSee('Acciones rapidas')
            ->assertSee('Nueva afiliacion')
            ->assertSee('Registrar pago')
            ->assertSee('Mini tienda')
            ->assertSee('Pago confirmado')
            ->assertSee('REC-001')
            ->assertDontSee('payment_confirmed')
            ->assertDontSee('mobile_login')
            ->assertSee('data-dashboard-sparklines', false)
            ->assertSee('data-dashboard-fullscreen', false)
            ->assertSee('Pantalla completa')
            ->assertSee('Base de datos · OK')
            ->assertSee('API movil · OK')
            ->assertSee('Mini Tienda · OK')
            ->assertDontSee('>Abrir<', false)
            ->assertDontSee('credentials_without_qr');

        $charts = $this->dashboardCharts($response->getContent());
        $sparklines = $this->dashboardSparklines($response->getContent());
        $this->assertSame('area', $charts['affiliations']['type']);
        $this->assertSame('area', $charts['revenue']['type']);
        $this->assertSame('donut', $charts['statuses']['type']);
        $this->assertCount(30, $charts['affiliations']['labels']);
        $this->assertContains('Afiliado activo', $charts['statuses']['labels']);
        $this->assertCount(30, $sparklines['affiliates']['series']);
        $this->assertSame(1, $sparklines['store']['series'][29]);
    }

    public function test_dashboard_actions_follow_role_permissions(): void
    {
        Cache::flush();

        $manager = User::factory()->create([
            'name' => 'GERENTE TEST',
            'role' => 'gerente',
            'user_type' => 'internal',
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Centro de operaciones')
            ->assertSee('Mini tienda')
            ->assertSee('Reportes')
            ->assertDontSee('Nuevo usuario')
            ->assertDontSee(route('admin.users.create'), false);
    }

    public function test_dashboard_is_personalized_for_secretary_cashier_and_readonly_roles(): void
    {
        Cache::flush();

        $secretary = User::factory()->create(['role' => 'secretaria', 'user_type' => 'internal', 'is_active' => true]);
        $cashier = User::factory()->create(['role' => 'cajero', 'user_type' => 'internal', 'is_active' => true]);
        $readonly = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal', 'is_active' => true]);

        $this->actingAs($secretary)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Centro de operaciones')
            ->assertSee('Registrar pago')
            ->assertSee('Afiliados activos')
            ->assertDontSee('Nuevo usuario')
            ->assertDontSee('Roles y permisos');

        $this->actingAs($cashier)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pagos hoy')
            ->assertSee('Recaudacion')
            ->assertSee('Registrar pago')
            ->assertDontSee('Mini tienda')
            ->assertDontSee('Nuevo usuario');

        $this->actingAs($readonly)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Centro de operaciones')
            ->assertSee('Mini tienda')
            ->assertSee('Reportes')
            ->assertDontSee('Registrar pago')
            ->assertDontSee('Nueva afiliacion')
            ->assertDontSee('Nuevo usuario');
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => 'SALUD', 'code' => 'SAL', 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'name' => 'PLAN BASE',
            'type' => 'independiente',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'currency' => 'BOB',
            'is_active' => true,
        ]);
        $person = Person::create(['full_name' => 'AFILIADA TEST', 'ci' => '123456']);
        $user = User::factory()->create([
            'person_id' => $person->id,
            'name' => 'AFILIADA TEST',
            'email' => 'afiliada.dashboard@test.local',
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'is_active' => true,
        ]);

        return Affiliate::create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'AFILIADA TEST',
            'ci' => '123456',
            'email' => $user->email,
            'registration_number' => 'REG-DASH-001',
            'verification_token' => 'dash-token',
            'status' => 'activo',
        ]);
    }

    private function dashboardCharts(string $html): array
    {
        return $this->dashboardJsonAttribute($html, 'data-dashboard-charts');
    }

    private function dashboardSparklines(string $html): array
    {
        return $this->dashboardJsonAttribute($html, 'data-dashboard-sparklines');
    }

    private function dashboardJsonAttribute(string $html, string $attribute): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute($attribute)) {
                return json_decode($element->getAttribute($attribute), true, flags: JSON_THROW_ON_ERROR);
            }
        }

        $this->fail("No se encontro el atributo {$attribute} del dashboard.");
    }
}
