<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateJewelDeliveryHistory;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\DigitalCredential;
use App\Models\Sector;
use App\Models\User;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AffiliateJewelDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_roles_can_mark_jewel_delivery(): void
    {
        foreach (['administrador', 'gerente', 'cajero'] as $role) {
            $affiliate = $this->affiliate($role);
            $actor = $this->internalUser($role);

            $this->actingAs($actor)
                ->patch(route('affiliates.jewel-delivery.deliver', $affiliate), [
                    'delivered_at' => '2026-09-24T10:30',
                ])
                ->assertRedirect();

            $affiliate->refresh();
            $this->assertSame($actor->id, $affiliate->jewel_delivered_by);
            $this->assertSame('2026-09-24 14:30:00', $affiliate->jewel_delivered_at->format('Y-m-d H:i:s'));
        }
    }

    public function test_user_without_permission_receives_403(): void
    {
        $affiliate = $this->affiliate();

        $this->actingAs($this->internalUser('secretaria'))
            ->patch(route('affiliates.jewel-delivery.deliver', $affiliate))
            ->assertForbidden();

        $this->assertNull($affiliate->fresh()->jewel_delivered_at);
    }

    public function test_delivered_by_is_authenticated_user_and_cannot_be_forged(): void
    {
        $affiliate = $this->affiliate();
        $actor = $this->internalUser('cajero');
        $forged = $this->internalUser('gerente');

        $this->actingAs($actor)
            ->patch(route('affiliates.jewel-delivery.deliver', $affiliate), [
                'delivered_at' => '2026-09-24T11:00',
                'delivered_by' => $forged->id,
                'jewel_delivered_by' => $forged->id,
            ])
            ->assertRedirect();

        $this->assertSame($actor->id, $affiliate->fresh()->jewel_delivered_by);
        $this->assertSame($actor->id, AffiliateJewelDeliveryHistory::firstOrFail()->performed_by);
    }

    public function test_delivery_date_can_be_changed_and_audited(): void
    {
        $affiliate = $this->affiliate();
        $actor = $this->internalUser('gerente');

        $this->travelTo('2026-09-24 13:00:00');

        $this->actingAs($actor)->patch(route('affiliates.jewel-delivery.deliver', $affiliate), [
            'delivered_at' => '2026-09-24T09:00',
        ])->assertRedirect();

        $this->actingAs($actor)->patch(route('affiliates.jewel-delivery.deliver', $affiliate), [
            'delivered_at' => '2026-09-24T12:45',
        ])->assertRedirect();

        $this->assertSame('2026-09-24 16:45:00', $affiliate->fresh()->jewel_delivered_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('affiliate_jewel_delivery_histories', ['action' => 'date_changed', 'performed_by' => $actor->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_jewel_delivery_date_changed']);
    }

    public function test_reversion_preserves_history_user_and_reason(): void
    {
        $affiliate = $this->affiliate();
        $actor = $this->internalUser('administrador');
        $this->actingAs($actor)->patch(route('affiliates.jewel-delivery.deliver', $affiliate))->assertRedirect();

        $this->actingAs($actor)
            ->patch(route('affiliates.jewel-delivery.revert', $affiliate), ['reason' => 'Entrega registrada por error'])
            ->assertRedirect();

        $this->assertNull($affiliate->fresh()->jewel_delivered_at);
        $this->assertSame(2, AffiliateJewelDeliveryHistory::where('affiliate_id', $affiliate->id)->count());
        $this->assertDatabaseHas('affiliate_jewel_delivery_histories', [
            'action' => 'reverted',
            'performed_by' => $actor->id,
            'reason' => 'Entrega registrada por error',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'affiliate_jewel_delivery_reverted']);
    }

    public function test_get_does_not_modify_jewel_delivery_state(): void
    {
        $affiliate = $this->affiliate();

        $this->actingAs($this->internalUser('cajero'))
            ->get(route('affiliates.jewel-delivery.deliver', $affiliate))
            ->assertStatus(405);

        $this->assertNull($affiliate->fresh()->jewel_delivered_at);
    }

    public function test_report_counts_filters_and_csv(): void
    {
        $sectorA = Sector::create(['name' => 'Sector A', 'code' => 'SA', 'is_active' => true]);
        $sectorB = Sector::create(['name' => 'Sector B', 'code' => 'SB', 'is_active' => true]);
        $planA = $this->plan($sectorA);
        $planB = $this->plan($sectorB);
        $actor = $this->internalUser('gerente');

        $delivered = $this->affiliate('delivered', $sectorA, $planA);
        $pending = $this->affiliate('pending', $sectorB, $planB);
        $delivered->forceFill(['jewel_delivered_at' => '2026-09-20 08:00:00', 'jewel_delivered_by' => $actor->id])->save();

        $response = $this->actingAs($actor)->get(route('reports.index', ['jewel_status' => 'delivered']));
        $response->assertOk()
            ->assertSee('Joyas entregadas')
            ->assertSee('1')
            ->assertSee($delivered->full_name)
            ->assertDontSee($pending->full_name);

        $csv = $this->actingAs($actor)->get(route('reports.jewels.csv', ['jewel_search' => $delivered->ci]));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Codigo afiliado', $content);
        $this->assertStringContainsString($delivered->full_name, $content);
        $this->assertStringNotContainsString($pending->full_name, $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }

    public function test_report_filters_jewel_delivery_by_bolivia_local_day(): void
    {
        $actor = $this->internalUser('gerente');
        $affiliate = $this->affiliate('timezone-filter');
        $affiliate->forceFill([
            'jewel_delivered_at' => '2026-09-24 02:30:00',
            'jewel_delivered_by' => $actor->id,
        ])->save();

        $this->actingAs($actor)
            ->get(route('reports.index', ['jewel_from' => '2026-09-23', 'jewel_to' => '2026-09-23']))
            ->assertOk()
            ->assertSee($affiliate->full_name);

        $this->actingAs($actor)
            ->get(route('reports.index', ['jewel_from' => '2026-09-24', 'jewel_to' => '2026-09-24']))
            ->assertOk()
            ->assertDontSee($affiliate->full_name);
    }

    public function test_jewel_delivery_does_not_change_payments_credentials_or_affiliate_status(): void
    {
        $affiliate = $this->affiliate();
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'expected_amount' => 120,
            'paid_amount' => 120,
            'status' => PaymentStatus::UNDER_REVIEW,
            'source' => 'office_cash',
        ]);
        $credential = DigitalCredential::create([
            'affiliate_id' => $affiliate->id,
            'qr_path' => 'credentials/qr/test.png',
            'generated_at' => now(),
        ]);

        $this->actingAs($this->internalUser('cajero'))
            ->patch(route('affiliates.jewel-delivery.deliver', $affiliate))
            ->assertRedirect();

        $this->assertSame('pendiente_pago', $affiliate->fresh()->status);
        $this->assertSame(PaymentStatus::UNDER_REVIEW, $payment->fresh()->status);
        $this->assertSame('credentials/qr/test.png', $credential->fresh()->qr_path);
    }

    private function affiliate(string $suffix = 'base', ?Sector $sector = null, ?AffiliationPlan $plan = null): Affiliate
    {
        $sector ??= Sector::create(['name' => 'Sector '.$suffix, 'code' => 'SEC'.strtoupper(substr(md5($suffix), 0, 6)), 'is_active' => true]);
        $plan ??= $this->plan($sector);
        $ci = 'CI'.strtoupper(substr(md5($suffix.microtime()), 0, 8));

        return Affiliate::create([
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'AFILIADO JOYA '.strtoupper($suffix),
            'ci' => $ci,
            'phone' => '70000000',
            'email' => strtolower($ci).'@test.local',
            'registration_number' => 'REG-'.strtoupper(substr(md5($ci), 0, 8)),
            'verification_token' => 'token-'.strtolower($ci),
            'status' => 'pendiente_pago',
        ]);
    }

    private function plan(Sector $sector): AffiliationPlan
    {
        return AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'Plan joya '.$sector->code,
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'is_active' => true,
        ]);
    }

    private function internalUser(string $role): User
    {
        return User::create([
            'name' => 'Usuario '.$role,
            'email' => $role.'-'.str()->uuid().'@test.local',
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('secret123'),
        ]);
    }
}
