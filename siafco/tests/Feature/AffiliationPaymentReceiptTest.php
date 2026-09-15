<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\AffiliationPaymentReceiptPresenter;
use App\Services\PaymentReceiptNumberService;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AffiliationPaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_cash_receipt_prints_before_approval_with_sol_and_pending_registration(): void
    {
        [$affiliate, $user, $sector, $plan] = $this->affiliateFixture(['registration_number' => null]);
        $cashier = $this->internalUser('cajero');
        $application = $this->publicApplication($affiliate, $user, $sector, $plan, 'SOL-20260907-CASH01');
        $payment = $this->payment($affiliate, [
            'public_affiliation_request_id' => $application->id,
            'payment_method' => 'efectivo',
            'source' => 'office_cash',
            'status' => PaymentStatus::PENDING,
            'registered_by' => $cashier->id,
        ]);

        $this->actingAs($cashier)->get(route('admin.payments.receipt', $payment))->assertOk();
        $html = $this->receiptHtml($payment);

        $this->assertStringContainsString('RECIBO DE PAGO', $html);
        $this->assertStringContainsString('SOL-20260907-CASH01', $html);
        $this->assertStringContainsString('PENDIENTE DE APROBACION', $html);
        $this->assertStringNotContainsString('Registro temporal', $html);
    }

    public function test_office_qr_under_review_receipt_prints_with_real_method_and_status(): void
    {
        [$affiliate] = $this->affiliateFixture(['registration_number' => null]);
        $cashier = $this->internalUser('caja');
        $payment = $this->payment($affiliate, [
            'payment_method' => 'qr',
            'reference_number' => 'QR-REV-001',
            'source' => 'office_qr',
            'status' => PaymentStatus::UNDER_REVIEW,
            'registered_by' => $cashier->id,
        ]);

        $this->actingAs($cashier)->get(route('admin.payments.receipt', $payment))->assertOk();
        $html = $this->receiptHtml($payment);

        $this->assertStringContainsString('QR / Transferencia en oficina', $html);
        $this->assertStringContainsString('EN REVISION', $html);
        $this->assertStringContainsString('QR-REV-001', $html);
    }

    public function test_reprint_after_approval_keeps_same_payment_and_sol_but_shows_definitive_registration(): void
    {
        [$affiliate, $user, $sector, $plan] = $this->affiliateFixture(['registration_number' => null]);
        $manager = $this->internalUser('gerente');
        $application = $this->publicApplication($affiliate, $user, $sector, $plan, 'SOL-20260907-APR001');
        $payment = $this->payment($affiliate, [
            'public_affiliation_request_id' => $application->id,
            'payment_method' => 'qr',
            'reference_number' => 'QR-APR-001',
            'source' => 'office_qr',
            'status' => PaymentStatus::UNDER_REVIEW,
        ]);
        $receiptNumber = $payment->receipt_number;
        $counts = $this->entityCounts();

        $this->actingAs($manager)->post(route('payments.confirm', $payment))->assertRedirect();
        $payment->refresh();
        $affiliate->refresh();

        $this->assertSame($receiptNumber, $payment->receipt_number);
        $this->assertSame($counts['payments'], AffiliationPayment::count());
        $this->assertSame($counts['affiliates'], Affiliate::count());
        $this->assertSame($counts['applications'], PublicAffiliationRequest::count());
        $this->assertNotNull($affiliate->registration_number);

        $html = $this->receiptHtml($payment);
        $this->assertStringContainsString('SOL-20260907-APR001', $html);
        $this->assertStringContainsString($affiliate->registration_number, $html);
        $this->assertStringContainsString('AFILIADO ACTIVO', $html);
    }

    public function test_direct_office_affiliation_receipt_does_not_create_artificial_sol(): void
    {
        [$affiliate] = $this->affiliateFixture(['registration_number' => null]);
        $payment = $this->payment($affiliate, [
            'payment_method' => 'efectivo',
            'source' => 'office_cash',
            'status' => PaymentStatus::UNDER_REVIEW,
        ]);

        $html = $this->receiptHtml($payment);

        $this->assertStringContainsString('No aplica', $html);
        $this->assertSame(0, PublicAffiliationRequest::count());
    }

    public function test_affiliate_detail_lists_only_own_payments_and_receipt_reprint_action(): void
    {
        [$affiliate] = $this->affiliateFixture(['registration_number' => 'SAL-000001']);
        [$other] = $this->affiliateFixture([
            'ci' => '900002',
            'email' => 'otra@siafco.test',
            'registration_number' => 'SAL-000002',
            'verification_token' => 'other-token',
        ]);
        $admin = $this->internalUser('administrador');
        $ownPayment = $this->payment($affiliate, ['reference_number' => 'OWN-001']);
        $otherPayment = $this->payment($other, ['reference_number' => 'OTHER-001']);

        $this->actingAs($admin)->get(route('affiliates.show', $affiliate))
            ->assertOk()
            ->assertSee('Pagos y recibos')
            ->assertSee($ownPayment->receipt_number)
            ->assertSee('Reimprimir')
            ->assertDontSee($otherPayment->receipt_number)
            ->assertDontSee('OTHER-001');
    }

    public function test_reprinting_receipt_is_read_only_for_financial_and_affiliation_entities(): void
    {
        [$affiliate] = $this->affiliateFixture(['registration_number' => 'SAL-000001']);
        $admin = $this->internalUser('administrador');
        $payment = $this->payment($affiliate);
        $counts = $this->entityCounts();

        $this->actingAs($admin)->get(route('admin.payments.receipt', $payment))->assertOk();
        $this->actingAs($admin)->get(route('admin.payments.receipt', $payment))->assertOk();

        $this->assertSame($counts['payments'], AffiliationPayment::count());
        $this->assertSame($counts['affiliates'], Affiliate::count());
        $this->assertSame($counts['applications'], PublicAffiliationRequest::count());
        $this->assertSame($payment->receipt_number, $payment->fresh()->receipt_number);
    }

    public function test_remote_pending_payment_keeps_existing_receipt_restriction(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $admin = $this->internalUser('administrador');
        $payment = $this->payment($affiliate, [
            'source' => 'web',
            'status' => PaymentStatus::UNDER_REVIEW,
            'receipt_number' => null,
        ], false);

        $this->actingAs($admin)->get(route('admin.payments.receipt', $payment))->assertNotFound();
    }

    public function test_rejected_office_payment_keeps_number_but_cannot_render_as_valid_receipt(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $manager = $this->internalUser('gerente');
        $payment = $this->payment($affiliate, [
            'source' => 'office_qr',
            'payment_method' => 'qr',
            'status' => PaymentStatus::UNDER_REVIEW,
        ]);
        $receiptNumber = $payment->receipt_number;

        $this->actingAs($manager)->post(route('payments.reject', $payment), [
            'rejection_reason' => 'OPERACION NO ENCONTRADA',
        ])->assertRedirect();

        $this->assertSame($receiptNumber, $payment->fresh()->receipt_number);
        $this->actingAs($manager)->get(route('admin.payments.receipt', $payment))->assertNotFound();
    }

    public function test_approved_request_resolves_affiliate_by_foreign_key_not_name_matching(): void
    {
        [$affiliate, $user, $sector, $plan] = $this->affiliateFixture([
            'full_name' => 'SULEMA CONDOR',
            'registration_number' => null,
        ]);
        $manager = $this->internalUser('gerente');
        $application = $this->publicApplication($affiliate, $user, $sector, $plan, 'SOL-20260906-SZROOM');
        $payment = $this->payment($affiliate, [
            'public_affiliation_request_id' => $application->id,
            'source' => 'manual_admin',
            'status' => PaymentStatus::UNDER_REVIEW,
        ]);

        $this->actingAs($manager)->post(route('payments.confirm', $payment))->assertRedirect();

        $this->assertSame($affiliate->id, $application->fresh()->affiliate_id);
        $this->assertSame($affiliate->fresh()->registration_number, $payment->fresh('publicRequest.affiliate')->publicRequest->affiliate->registration_number);
    }

    public function test_web_requests_keep_sol_and_show_definitive_registration_when_related_affiliate_exists(): void
    {
        [$affiliate, $user, $sector, $plan] = $this->affiliateFixture(['registration_number' => 'SAL-000077']);
        $admin = $this->internalUser('administrador');
        $application = $this->publicApplication($affiliate, $user, $sector, $plan, 'SOL-WEB-KEEP-001', 'approved');
        $this->payment($affiliate, [
            'public_affiliation_request_id' => $application->id,
            'transaction_number' => 'TRX-WEB-001',
            'source' => 'web',
            'status' => PaymentStatus::CONFIRMED,
        ]);

        $this->actingAs($admin)->get(route('public-affiliation.admin.index'))
            ->assertOk()
            ->assertSee('Solicitudes Web')
            ->assertSee('SOL-WEB-KEEP-001')
            ->assertSee('Registro definitivo: SAL-000077')
            ->assertSee('TRX-WEB-001');
    }

    public function test_request_review_separates_uploaded_voucher_from_generated_receipt(): void
    {
        [$affiliate, $user, $sector, $plan] = $this->affiliateFixture(['registration_number' => null]);
        $admin = $this->internalUser('administrador');
        $application = $this->publicApplication($affiliate, $user, $sector, $plan, 'SOL-DOCS-001');
        $payment = $this->payment($affiliate, [
            'public_affiliation_request_id' => $application->id,
            'source' => 'manual_admin',
            'status' => PaymentStatus::UNDER_REVIEW,
            'voucher_path' => 'affiliation-receipts/fake.pdf',
        ]);

        $this->actingAs($admin)->get(route('public-affiliation.admin.show', $application))
            ->assertOk()
            ->assertSee('Documentos del pago')
            ->assertSee('Comprobante presentado')
            ->assertSee('Descargar comprobante')
            ->assertSee('Recibo de pago')
            ->assertSee($payment->receipt_number)
            ->assertSee('PENDIENTE DE APROBACIÓN')
            ->assertSee('Ver recibo')
            ->assertSee('Imprimir');
    }

    public function test_all_payments_lists_real_sources_and_filters_by_source_receipt_and_registration(): void
    {
        [$webAffiliate, $webUser, $sector, $plan] = $this->affiliateFixture([
            'registration_number' => 'SAL-WEB-001',
            'email' => 'web-origin@siafco.test',
            'ci' => '810001',
            'verification_token' => 'web-origin-token',
        ]);
        [$officeAffiliate] = $this->affiliateFixture([
            'registration_number' => 'SAL-OFF-001',
            'email' => 'office-origin@siafco.test',
            'ci' => '810002',
            'verification_token' => 'office-origin-token',
        ]);
        [$mobileAffiliate] = $this->affiliateFixture([
            'registration_number' => 'SAL-APP-001',
            'email' => 'app-origin@siafco.test',
            'ci' => '810003',
            'verification_token' => 'app-origin-token',
        ]);
        $admin = $this->internalUser('administrador');
        $application = $this->publicApplication($webAffiliate, $webUser, $sector, $plan, 'SOL-WEB-LIST-001', 'approved');
        $webPayment = $this->payment($webAffiliate, [
            'public_affiliation_request_id' => $application->id,
            'source' => 'web',
            'status' => PaymentStatus::CONFIRMED,
            'reference_number' => 'WEB-LIST-001',
        ]);
        $officePayment = $this->payment($officeAffiliate, [
            'source' => 'office_cash',
            'status' => PaymentStatus::PENDING,
            'reference_number' => 'OFF-LIST-001',
        ]);
        $mobilePayment = $this->payment($mobileAffiliate, [
            'source' => 'mobile',
            'status' => PaymentStatus::CONFIRMED,
            'reference_number' => 'APP-LIST-001',
        ]);

        $this->actingAs($admin)->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Todos los Pagos')
            ->assertSee('WEB')
            ->assertSee('OFICINA')
            ->assertSee('APP');

        $this->actingAs($admin)->get(route('payments.index', ['source' => 'office_cash']))
            ->assertOk()
            ->assertSee($officePayment->receipt_number)
            ->assertDontSee($webPayment->receipt_number)
            ->assertDontSee($mobilePayment->receipt_number);

        $this->actingAs($admin)->get(route('payments.index', ['search' => $webPayment->receipt_number]))
            ->assertOk()
            ->assertSee($webPayment->receipt_number)
            ->assertDontSee($officePayment->receipt_number);

        $this->actingAs($admin)->get(route('payments.index', ['search' => 'SAL-OFF-001']))
            ->assertOk()
            ->assertSee($officePayment->receipt_number)
            ->assertDontSee($webPayment->receipt_number);
    }

    public function test_approved_without_payment_is_only_detected_and_not_modified(): void
    {
        [$affiliate, $user, $sector, $plan] = $this->affiliateFixture(['registration_number' => 'SAL-000003']);
        $application = $this->publicApplication($affiliate, $user, $sector, $plan, 'SOL-NOPAY-001', 'approved');

        $this->assertSame(1, PublicAffiliationRequest::where('status', 'approved')->doesntHave('payment')->count());
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame(0, AffiliationPayment::count());
    }

    private function receiptHtml(AffiliationPayment $payment): string
    {
        return view('payments.receipt', [
            'payment' => $payment->fresh(),
            'receipt' => app(AffiliationPaymentReceiptPresenter::class)->present($payment->fresh()),
            'institution' => InstitutionalSetting::current(),
            'logoSrc' => null,
        ])->render();
    }

    private function payment(Affiliate $affiliate, array $overrides = [], bool $assignReceipt = true): AffiliationPayment
    {
        $payment = AffiliationPayment::create(array_merge([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'efectivo',
            'reference_number' => 'PAY-001',
            'status' => PaymentStatus::UNDER_REVIEW,
            'source' => 'manual_admin',
            'paid_at' => now(),
        ], $overrides));

        if ($assignReceipt) {
            app(PaymentReceiptNumberService::class)->assignIfMissing($payment);
        }

        return $payment->fresh();
    }

    private function publicApplication(
        Affiliate $affiliate,
        User $user,
        Sector $sector,
        AffiliationPlan $plan,
        string $code,
        string $status = 'payment_submitted'
    ): PublicAffiliationRequest {
        return PublicAffiliationRequest::create([
            'person_id' => $affiliate->person_id,
            'affiliate_id' => $affiliate->id,
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'public_token' => fake()->uuid(),
            'request_code' => $code,
            'amount_due' => 120,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    private function affiliateFixture(array $overrides = []): array
    {
        $sector = Sector::firstOrCreate(['code' => 'SAL'], ['name' => 'Salud', 'is_active' => true]);
        $plan = AffiliationPlan::firstOrCreate([
            'name' => 'Completo',
        ], [
            'sector_id' => $sector->id,
            'type' => 'independiente',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'currency' => 'BOB',
            'is_active' => true,
        ]);
        $ci = $overrides['ci'] ?? '900001';
        $email = $overrides['email'] ?? 'afiliada.recibo@siafco.test';
        $person = Person::create([
            'full_name' => $overrides['full_name'] ?? 'AFILIADA RECIBO',
            'ci' => $ci,
            'email' => $email,
        ]);
        $user = User::factory()->create([
            'person_id' => $person->id,
            'name' => $person->full_name,
            'email' => $email,
            'password' => Hash::make('Secret1234'),
            'role' => 'afiliado',
            'user_type' => 'affiliate',
        ]);
        $affiliate = Affiliate::create(array_merge([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => $person->full_name,
            'ci' => $ci,
            'email' => $email,
            'registration_number' => 'SAL-000001',
            'verification_token' => fake()->uuid(),
            'status' => 'pendiente_pago',
        ], $overrides));

        return [$affiliate, $user, $sector, $plan];
    }

    private function internalUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
        ]);
    }

    private function entityCounts(): array
    {
        return [
            'payments' => AffiliationPayment::count(),
            'affiliates' => Affiliate::count(),
            'applications' => PublicAffiliationRequest::count(),
        ];
    }
}
