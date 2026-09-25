<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\DigitalCredential;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliationReportSummaryService;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationReportSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_uses_real_payment_statuses_and_amounts(): void
    {
        $affiliate = $this->affiliate();
        $this->payment($affiliate, PaymentStatus::UNDER_REVIEW, 100, ['source' => 'web']);
        $this->payment($affiliate, PaymentStatus::PENDING, 75, ['source' => 'office_cash']);
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 120, ['source' => 'web']);
        $this->payment($affiliate, 'confirmado', 80, ['source' => 'office_qr']);
        $this->payment($affiliate, PaymentStatus::REJECTED, 60);
        $this->payment($affiliate, PaymentStatus::VOIDED, 40);

        $summary = app(AffiliationReportSummaryService::class)->summary();

        $this->assertSame(2, $summary['pending_payments']);
        $this->assertSame(2, $summary['confirmed_payments']);
        $this->assertEquals(200, (float) $summary['confirmed_income']);
    }

    public function test_rejected_pending_and_voided_do_not_increment_confirmed_income(): void
    {
        $affiliate = $this->affiliate();
        $this->payment($affiliate, PaymentStatus::UNDER_REVIEW, 100);
        $this->payment($affiliate, PaymentStatus::REJECTED, 100);
        $this->payment($affiliate, PaymentStatus::VOIDED, 100);

        $summary = app(AffiliationReportSummaryService::class)->summary();

        $this->assertSame(0, $summary['confirmed_payments']);
        $this->assertEquals(0, (float) $summary['confirmed_income']);
    }

    public function test_credentials_total_matches_generated_credentials_and_is_not_date_filtered(): void
    {
        $affiliate = $this->affiliate();
        $otherAffiliate = $this->affiliate();
        DigitalCredential::create(['affiliate_id' => $affiliate->id, 'status' => 'vigente', 'qr_path' => 'a.png', 'generated_at' => '2026-09-20 12:00:00']);
        DigitalCredential::create(['affiliate_id' => $otherAffiliate->id, 'status' => 'suspendida', 'qr_path' => 'b.png', 'generated_at' => '2026-09-24 12:00:00']);

        $summary = app(AffiliationReportSummaryService::class)->summary('2026-09-24', '2026-09-24');

        $this->assertSame(2, $summary['credentials']);
    }

    public function test_date_filters_use_bolivia_day_for_real_timestamps(): void
    {
        $affiliate = $this->affiliate();
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 120, [
            'confirmed_at' => '2026-09-24 02:30:00',
            'paid_at' => '2026-09-24 00:00:00',
        ]);
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 90, [
            'confirmed_at' => '2026-09-24 14:30:00',
            'paid_at' => '2026-09-24 00:00:00',
        ]);

        $previousLocalDay = app(AffiliationReportSummaryService::class)->summary('2026-09-23', '2026-09-23');
        $currentLocalDay = app(AffiliationReportSummaryService::class)->summary('2026-09-24', '2026-09-24');

        $this->assertSame(1, $previousLocalDay['confirmed_payments']);
        $this->assertEquals(120, (float) $previousLocalDay['confirmed_income']);
        $this->assertSame(1, $currentLocalDay['confirmed_payments']);
        $this->assertEquals(90, (float) $currentLocalDay['confirmed_income']);
    }

    public function test_from_to_and_range_filters_apply_to_pending_and_confirmed_cards(): void
    {
        $affiliate = $this->affiliate();
        $this->payment($affiliate, PaymentStatus::UNDER_REVIEW, 50, ['submitted_at' => '2026-09-24 05:00:00']);
        $this->payment($affiliate, PaymentStatus::UNDER_REVIEW, 50, ['submitted_at' => '2026-09-25 05:00:00']);
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 70, ['confirmed_at' => '2026-09-24 05:00:00']);
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 80, ['confirmed_at' => '2026-09-25 05:00:00']);

        $from = app(AffiliationReportSummaryService::class)->summary('2026-09-24');
        $to = app(AffiliationReportSummaryService::class)->summary(null, '2026-09-24');
        $range = app(AffiliationReportSummaryService::class)->summary('2026-09-24', '2026-09-24');

        $this->assertSame(2, $from['pending_payments']);
        $this->assertSame(2, $from['confirmed_payments']);
        $this->assertSame(1, $to['pending_payments']);
        $this->assertSame(1, $to['confirmed_payments']);
        $this->assertSame(1, $range['pending_payments']);
        $this->assertSame(1, $range['confirmed_payments']);
        $this->assertEquals(70, (float) $range['confirmed_income']);
    }

    public function test_paid_at_civil_date_does_not_move_confirmed_payment_into_filter_without_confirmed_at(): void
    {
        $affiliate = $this->affiliate();
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 120, [
            'confirmed_at' => null,
            'paid_at' => '2026-09-24 00:00:00',
        ]);

        $summary = app(AffiliationReportSummaryService::class)->summary('2026-09-24', '2026-09-24');

        $this->assertSame(0, $summary['confirmed_payments']);
        $this->assertEquals(0, (float) $summary['confirmed_income']);
    }

    public function test_report_screen_uses_summary_values_and_keeps_support_and_jewel_blocks(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
        $affiliate = $this->affiliate();
        $this->payment($affiliate, PaymentStatus::UNDER_REVIEW, 100);
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 125);
        DigitalCredential::create(['affiliate_id' => $affiliate->id, 'status' => 'vigente', 'qr_path' => 'credential.png', 'generated_at' => '2026-09-24 14:00:00']);

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Pagos pendientes')
            ->assertSee('Pagos confirmados')
            ->assertSee('Credenciales generadas')
            ->assertSee('Bs 125.00')
            ->assertSee('Soporte y captación')
            ->assertSee('Entrega de joyas');
    }

    public function test_pdf_route_uses_same_summary_service(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
        $affiliate = $this->affiliate();
        $this->payment($affiliate, PaymentStatus::CONFIRMED, 150, ['confirmed_at' => '2026-09-24 14:00:00']);

        $this->actingAs($admin)
            ->get(route('reports.pdf', ['from' => '2026-09-24', 'to' => '2026-09-24']))
            ->assertOk();

        $summary = app(AffiliationReportSummaryService::class)->summary('2026-09-24', '2026-09-24');
        $this->assertSame(1, $summary['confirmed_payments']);
        $this->assertEquals(150, (float) $summary['confirmed_income']);
    }

    private function affiliate(): Affiliate
    {
        $code = strtoupper(substr((string) str()->uuid(), 0, 6));
        $sector = Sector::create(['name' => 'Sector reporte '.$code, 'code' => $code, 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'Plan reporte',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'is_active' => true,
        ]);

        return Affiliate::create([
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'AFILIADO REPORTE',
            'ci' => 'REP'.str()->random(8),
            'phone' => '70000000',
            'email' => str()->uuid().'@test.local',
            'status' => 'activo',
        ]);
    }

    private function payment(Affiliate $affiliate, string $status, float $amount, array $overrides = []): AffiliationPayment
    {
        return AffiliationPayment::create(array_merge([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => $amount,
            'paid_amount' => $amount,
            'expected_amount' => $amount,
            'currency' => 'BOB',
            'status' => $status,
            'source' => 'web',
            'payment_method' => 'transferencia',
            'payment_date' => '2026-09-24',
            'paid_at' => '2026-09-24 00:00:00',
            'submitted_at' => '2026-09-24 14:00:00',
            'confirmed_at' => PaymentStatus::isConfirmed($status) ? '2026-09-24 14:00:00' : null,
        ], $overrides));
    }
}
