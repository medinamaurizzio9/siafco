<?php

namespace Tests\Feature;

use App\Events\PaymentConfirmed;
use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\CredentialService;
use App\Services\PaymentBalanceService;
use App\Services\PaymentLifecycleService;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ManualTreasuryPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_registers_edits_confirms_and_cannot_void_manual_payment(): void
    {
        Storage::fake('local');
        [$affiliate] = $this->affiliateFixture();
        $secretary = $this->internalUser('secretaria');
        $manager = $this->internalUser('gerente');

        $response = $this->actingAs($secretary)->post(route('payments.store'), $this->paymentPayload($affiliate, [
            'voucher' => UploadedFile::fake()->image('voucher.jpg'),
        ]));

        $response->assertSessionHasNoErrors();
        $payment = AffiliationPayment::firstOrFail();
        $response->assertRedirect(route('payments.show', $payment));
        $this->assertSame('manual_admin', $payment->source);
        $this->assertSame($secretary->id, $payment->registered_by);
        $this->assertNotNull($payment->voucher_path);
        Storage::disk('local')->assertExists($payment->voucher_path);

        $this->actingAs($secretary)->put(route('payments.update', $payment), $this->paymentPayload($affiliate, [
            'amount' => '120.00',
            'reference_number' => 'REF-EDIT',
        ]))->assertRedirect(route('payments.show', $payment));
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_updated', 'auditable_id' => $payment->id]);

        $this->actingAs($manager)->post(route('payments.confirm', $payment))->assertRedirect();
        $payment->refresh();
        $this->assertSame('confirmed', $payment->status);
        $this->assertSame($manager->id, $payment->confirmed_by);
        $this->assertNotNull($payment->confirmed_at);
        $this->assertNotNull($payment->receipt_number);
        $this->assertSame('activo', $affiliate->fresh()->status);
        $this->assertDatabaseHas('digital_credentials', ['affiliate_id' => $affiliate->id]);
        $this->assertSame(120.0, app(PaymentBalanceService::class)->confirmedAmount($affiliate->fresh()));
        $this->assertSame(0.0, app(PaymentBalanceService::class)->balance($affiliate->fresh('plan')));

        $this->actingAs($secretary)->post(route('payments.void', $payment), [
            'confirmation' => 'ANULAR',
            'void_reason' => 'ERROR OPERATIVO',
        ])->assertForbidden();
    }

    public function test_confirmation_orchestrates_public_request_mobile_capabilities_and_is_idempotent(): void
    {
        [$affiliate, $user] = $this->affiliateFixture(['registration_number' => null, 'verification_token' => 'token-original']);
        $secretary = $this->internalUser('secretaria');
        $manager = $this->internalUser('gerente');
        $request = PublicAffiliationRequest::create([
            'person_id' => $affiliate->person_id,
            'affiliate_id' => $affiliate->id,
            'user_id' => $user->id,
            'sector_id' => $affiliate->sector_id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'public_token' => 'public-token',
            'request_code' => 'SOL-001',
            'amount_due' => 120,
            'status' => 'payment_submitted',
            'submitted_at' => now(),
        ]);
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'public_affiliation_request_id' => $request->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'qr',
            'reference_number' => 'REF-PUBLIC',
            'status' => 'under_review',
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        $this->actingAs($manager)->post(route('payments.confirm', $payment))->assertRedirect();
        $payment->refresh();
        $affiliate->refresh();
        $receipt = $payment->receipt_number;
        $credentialId = $affiliate->credential()->value('id');

        $this->assertSame('confirmed', $payment->status);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame('activo', $affiliate->status);
        $this->assertNotNull($affiliate->registration_number);
        $this->assertSame('token-original', $affiliate->verification_token);
        $this->assertDatabaseCount('digital_credentials', 1);

        $this->actingAs($manager)->post(route('payments.confirm', $payment))
            ->assertSessionHasErrors('payment');
        $this->assertSame($receipt, $payment->fresh()->receipt_number);
        $this->assertSame($credentialId, $affiliate->credential()->value('id'));
        $this->assertSame(1, AuditLog::where('action', 'payment_confirmed')->where('auditable_id', $payment->id)->count());

        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/mobile/v1/me')->assertOk()
            ->assertJsonPath('data.profile.affiliate.status', 'activo')
            ->assertJsonPath('data.profile.affiliate.access_level', 'full');
        $this->getJson('/api/mobile/v1/me/affiliation-request')->assertOk()
            ->assertJsonPath('data.affiliation_request.payment_status', 'confirmed')
            ->assertJsonPath('data.affiliation_request.capabilities.can_submit_payment', false)
            ->assertJsonPath('data.affiliation_request.capabilities.can_view_credential', true)
            ->assertJsonPath('data.affiliation_request.payment.status', 'confirmed');
        $this->getJson('/api/mobile/v1/me/payments')->assertOk()
            ->assertJsonPath('data.payments.0.status', 'confirmed');
        $this->getJson('/api/mobile/v1/me/credential')->assertOk();
    }

    public function test_payment_confirmed_event_is_dispatched_after_successful_confirmation(): void
    {
        Event::fake([PaymentConfirmed::class]);
        [$affiliate] = $this->affiliateFixture();
        $admin = $this->internalUser('administrador');
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'efectivo',
            'status' => 'pending',
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        app(PaymentLifecycleService::class)->confirm($payment, $admin);

        Event::assertDispatched(PaymentConfirmed::class);
    }

    public function test_partial_confirmation_does_not_activate_and_blocked_user_stays_blocked(): void
    {
        [$affiliate, $user] = $this->affiliateFixture();
        $user->update(['is_active' => false, 'email' => 'blocked@siafco.test', 'must_change_password' => true]);
        $oldPassword = $user->password;
        $admin = $this->internalUser('administrador');
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 60,
            'paid_amount' => 60,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'efectivo',
            'status' => 'pending',
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('payments.confirm', $payment))->assertRedirect();

        $this->assertSame('confirmed', $payment->fresh()->status);
        $this->assertSame('pendiente_pago', $affiliate->fresh()->status);
        $this->assertDatabaseCount('digital_credentials', 0);
        $this->assertSame(60.0, app(PaymentBalanceService::class)->balance($affiliate->fresh('plan')));
        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertSame('blocked@siafco.test', $user->email);
        $this->assertSame($oldPassword, $user->password);
        $this->assertTrue($user->must_change_password);
    }

    public function test_confirmation_rolls_back_when_credential_generation_fails(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $admin = $this->internalUser('administrador');
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'efectivo',
            'status' => 'pending',
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);
        $credentials = Mockery::mock(CredentialService::class);
        $credentials->shouldReceive('generate')->once()->andThrow(new \RuntimeException('credential failed'));
        $this->app->instance(CredentialService::class, $credentials);

        try {
            app(PaymentLifecycleService::class)->confirm($payment, $admin);
            $this->fail('Expected credential failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('credential failed', $exception->getMessage());
        }

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->confirmed_at);
        $this->assertSame('pendiente_pago', $affiliate->fresh()->status);
        $this->assertDatabaseCount('digital_credentials', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'payment_confirmed', 'auditable_id' => $payment->id]);
    }

    public function test_payment_confirmed_listener_failure_does_not_break_confirmation(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $admin = $this->internalUser('administrador');
        Event::listen(PaymentConfirmed::class, fn () => throw new \RuntimeException('listener failed'));
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'efectivo',
            'status' => 'pending',
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        app(PaymentLifecycleService::class)->confirm($payment, $admin);

        $this->assertSame('confirmed', $payment->fresh()->status);
        $this->assertSame('activo', $affiliate->fresh()->status);
    }

    public function test_reject_void_receipt_dashboard_and_mobile_api_are_safe(): void
    {
        Storage::fake('local');
        [$affiliate, $user] = $this->affiliateFixture();
        $secretary = $this->internalUser('secretaria');
        $manager = $this->internalUser('gerente');
        $admin = $this->internalUser('administrador');
        $cashier = $this->internalUser('cajero');

        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'transferencia',
            'reference_number' => 'REF-VOID',
            'voucher_path' => UploadedFile::fake()->image('voucher.jpg')->storeAs('payments/vouchers', 'voucher.jpg', 'local'),
            'status' => 'pending',
            'source' => 'manual_admin',
            'registered_by' => $cashier->id,
            'paid_at' => now(),
        ]);

        $this->actingAs($manager)->post(route('payments.reject', $payment), [
            'rejection_reason' => 'COMPROBANTE ILEGIBLE',
        ])->assertRedirect();
        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertSame($manager->id, $payment->rejected_by);
        Storage::disk('local')->assertExists($payment->voucher_path);

        $second = $payment->replicate(['rejection_reason', 'rejected_by', 'rejected_at']);
        $second->status = 'pending';
        $second->reference_number = 'REF-CONFIRM';
        $second->save();

        $this->actingAs($cashier)->post(route('payments.confirm', $second))->assertForbidden();
        $this->actingAs($admin)->post(route('payments.confirm', $second))->assertRedirect();
        $this->actingAs($secretary)->get(route('payments.receipt.download', $second))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($cashier)->post(route('payments.void', $second), [
            'confirmation' => 'ANULAR',
            'void_reason' => 'ERROR',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('payments.void', $second), [
            'confirmation' => 'ANULAR',
            'void_reason' => 'PAGO DUPLICADO',
        ])->assertRedirect();
        $this->assertSame('voided', $second->fresh()->status);
        $this->assertSame('pendiente_pago', $affiliate->fresh()->status);

        $this->actingAs($secretary)->get(route('payments.voucher', $payment))->assertOk();
        $this->actingAs($secretary)->get(route('payments.receipt.download', $payment))->assertNotFound();
        $this->actingAs($secretary)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Centro de operaciones')
            ->assertSee('Recaudacion');

        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/mobile/v1/me/payments')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payments.0.source', 'manual_admin')
            ->assertJsonMissing(['voucher_path'])
            ->assertJsonMissing(['registered_by']);

        $internal = $this->internalUser('consulta');
        Sanctum::actingAs($internal, ['*']);
        $this->getJson('/api/mobile/v1/me/payments')->assertForbidden()->assertJsonPath('success', false);

        $auditPayload = AuditLog::pluck('metadata')->filter()->map(fn ($metadata) => json_encode($metadata))->implode(' ');
        $this->assertStringNotContainsString('payments/vouchers', $auditPayload);
    }

    public function test_no_physical_payment_deletion_route_exists(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $admin = $this->internalUser('administrador');
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'amount' => 10,
            'paid_amount' => 10,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->delete('/pagos/'.$payment->id)->assertStatus(405);
        $this->assertDatabaseHas('affiliation_payments', ['id' => $payment->id]);
    }

    public function test_every_new_manual_payment_method_enters_review_and_keeps_historical_payments_unchanged(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $secretary = $this->internalUser('secretaria');
        $historical = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'efectivo',
            'status' => PaymentStatus::CONFIRMED,
            'source' => 'manual_admin',
            'confirmed_at' => now()->subDay(),
            'paid_at' => now()->subDay(),
        ]);

        foreach (['efectivo', 'qr', 'transferencia'] as $index => $method) {
            $this->actingAs($secretary)->post(route('payments.store'), $this->paymentPayload($affiliate, [
                'payment_method' => $method,
                'reference_number' => 'REVIEW-'.$index,
                'transaction_number' => 'TRX-REVIEW-'.$index,
                'status' => PaymentStatus::PENDING,
            ]))->assertSessionHasNoErrors();

            $payment = AffiliationPayment::where('reference_number', 'REVIEW-'.$index)->firstOrFail();
            $this->assertSame(PaymentStatus::UNDER_REVIEW, $payment->status);
            $this->assertSame($secretary->id, $payment->registered_by);
            $this->assertNull($payment->confirmed_by);
            $this->assertMatchesRegularExpression('/^REC-\d{4}-\d{6}$/', $payment->receipt_number);
        }

        $this->assertSame(PaymentStatus::CONFIRMED, $historical->fresh()->status);
        $this->assertSame('pendiente_pago', $affiliate->fresh()->status);
    }

    public function test_manual_payment_interprets_browser_timezone_and_keeps_created_at_system_time(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $secretary = $this->internalUser('secretaria');
        $this->travelTo('2026-09-28 20:00:00');

        $this->actingAs($secretary)->post(route('payments.store'), $this->paymentPayload($affiliate, [
            'payment_method' => 'qr',
            'paid_at' => '2026-09-28T09:20',
            'browser_timezone' => 'America/La_Paz',
            'reference_number' => 'TZ-REF',
            'transaction_number' => 'TZ-TRX',
        ]))->assertSessionHasNoErrors();

        $payment = AffiliationPayment::where('transaction_number', 'TZ-TRX')->firstOrFail();
        $this->assertSame('2026-09-28 13:20:00', $payment->paid_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('America/La_Paz', $payment->payment_timezone);
        $this->assertSame('2026-09-28 20:00:00', $payment->created_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_invalid_browser_timezone_is_rejected(): void
    {
        [$affiliate] = $this->affiliateFixture();
        $secretary = $this->internalUser('secretaria');

        $this->actingAs($secretary)->post(route('payments.store'), $this->paymentPayload($affiliate, [
            'payment_method' => 'qr',
            'browser_timezone' => 'Mars/La_Paz',
        ]))->assertSessionHasErrors('browser_timezone');
    }

    public function test_duplicate_transaction_number_is_rejected_across_legacy_fields(): void
    {
        Storage::fake('local');
        [$affiliate] = $this->affiliateFixture();
        $secretary = $this->internalUser('secretaria');

        AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'qr',
            'reference_number' => 'WEB-LEGACY-001',
            'status' => PaymentStatus::UNDER_REVIEW,
            'source' => 'web',
            'paid_at' => now(),
        ]);

        $this->actingAs($secretary)->post(route('payments.store'), $this->paymentPayload($affiliate, [
            'payment_method' => 'transferencia',
            'transaction_number' => ' WEB-LEGACY-001 ',
            'reference_number' => null,
        ]))->assertSessionHasErrors('transaction_number');
    }

    public function test_cashier_can_upload_and_replace_pending_voucher_without_confirm_permission(): void
    {
        Storage::fake('local');
        [$affiliate] = $this->affiliateFixture();
        $cashier = $this->internalUser('cajero');
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'qr',
            'transaction_number' => 'DOC-001',
            'status' => PaymentStatus::UNDER_REVIEW,
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        $this->actingAs($cashier)->post(route('payments.proof', $payment), [
            'voucher' => UploadedFile::fake()->image('voucher.jpg', 800, 800),
        ])->assertRedirect();
        $firstPath = $payment->fresh()->voucher_path;
        Storage::disk('local')->assertExists($firstPath);

        $this->actingAs($cashier)->post(route('payments.proof', $payment), [
            'voucher' => UploadedFile::fake()->image('voucher-2.png', 800, 800),
        ])->assertRedirect();
        $payment->refresh();

        $this->assertNotSame($firstPath, $payment->voucher_path);
        Storage::disk('local')->assertExists($payment->voucher_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_voucher_uploaded', 'auditable_id' => $payment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_voucher_replaced', 'auditable_id' => $payment->id]);
        $this->actingAs($cashier)->post(route('payments.confirm', $payment))->assertForbidden();
    }

    public function test_cashier_cannot_replace_confirmed_voucher_and_unauthorized_user_cannot_replace(): void
    {
        Storage::fake('local');
        [$affiliate] = $this->affiliateFixture();
        $cashier = $this->internalUser('cajero');
        $viewer = $this->internalUser('consulta');
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'qr',
            'transaction_number' => 'CONF-001',
            'voucher_path' => UploadedFile::fake()->image('voucher.jpg')->storeAs('payments/vouchers', 'original.jpg', 'local'),
            'status' => PaymentStatus::CONFIRMED,
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        $this->actingAs($cashier)->post(route('payments.proof', $payment), [
            'voucher' => UploadedFile::fake()->image('nuevo.jpg', 800, 800),
        ])->assertForbidden();

        $payment->update(['status' => PaymentStatus::UNDER_REVIEW]);
        $this->actingAs($viewer)->post(route('payments.proof', $payment), [
            'voucher' => UploadedFile::fake()->image('nuevo.jpg', 800, 800),
        ])->assertForbidden();
    }

    public function test_voucher_service_processes_large_images_keeps_small_images_and_preserves_pdf(): void
    {
        Storage::fake('local');
        $service = app(\App\Services\PaymentVoucherService::class);

        $large = $service->store(UploadedFile::fake()->image('large.jpg', 2600, 1800)->size(900), 'voucher');
        $this->assertStringEndsWith('.webp', $large);
        Storage::disk('local')->assertExists($large);
        $this->assertSame('image/webp', mime_content_type(Storage::disk('local')->path($large)));

        $small = $service->store(UploadedFile::fake()->image('small.png', 640, 480)->size(120), 'voucher');
        $this->assertStringEndsWith('.png', $small);
        Storage::disk('local')->assertExists($small);

        $pdf = $service->store(UploadedFile::fake()->create('voucher.pdf', 100, 'application/pdf'), 'voucher');
        $this->assertStringEndsWith('.pdf', $pdf);
        Storage::disk('local')->assertExists($pdf);
    }

    public function test_fake_jpg_is_rejected_and_legacy_transaction_renders_once(): void
    {
        Storage::fake('local');
        [$affiliate] = $this->affiliateFixture();
        $secretary = $this->internalUser('secretaria');
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'transferencia',
            'reference_number' => 'LEGACY-REF-ONLY',
            'status' => PaymentStatus::UNDER_REVIEW,
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        $this->actingAs($secretary)->post(route('payments.proof', $payment), [
            'voucher' => UploadedFile::fake()->createWithContent('fake.jpg', 'no soy imagen'),
        ])->assertSessionHasErrors('voucher');

        $html = $this->actingAs($secretary)->get(route('payments.show', $payment))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'N.º de transacción'));
        $this->assertStringContainsString('LEGACY-REF-ONLY', $html);
        $this->assertStringNotContainsString('Referencia interna', $html);
    }

    private function affiliateFixture(array $overrides = []): array
    {
        $sector = Sector::create(['name' => 'Salud', 'code' => 'SAL', 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'name' => 'Completo',
            'type' => 'independiente',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'currency' => 'BOB',
            'is_active' => true,
        ]);
        $person = Person::create(['full_name' => 'AFILIADA PRUEBA', 'ci' => '900001']);
        $user = User::factory()->create([
            'name' => 'AFILIADA PRUEBA',
            'email' => 'afiliada.tesoreria@siafco.test',
            'password' => Hash::make('Secret1234'),
            'role' => 'afiliado',
            'user_type' => 'affiliate',
        ]);
        $affiliate = Affiliate::create(array_merge([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'AFILIADA PRUEBA',
            'ci' => '900001',
            'email' => $user->email,
            'registration_number' => 'SAL-000001',
            'verification_token' => 'test-token',
            'status' => 'pendiente_pago',
        ], $overrides));

        return [$affiliate, $user];
    }

    private function internalUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
        ]);
    }

    private function paymentPayload(Affiliate $affiliate, array $overrides = []): array
    {
        $payload = array_merge([
            'affiliate_id' => $affiliate->id,
            'amount' => '120.00',
            'currency' => 'BOB',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'browser_timezone' => 'America/La_Paz',
            'payment_method' => 'transferencia',
            'bank_name' => 'BANCO TEST',
            'reference_number' => 'REF-001',
            'transaction_number' => 'TRX-001',
            'observations' => 'PAGO MANUAL',
            'status' => 'pending',
        ], $overrides);

        if (in_array($payload['payment_method'] ?? null, ['qr', 'transferencia', 'deposito'], true)
            && ! array_key_exists('voucher', $overrides)) {
            $payload['voucher'] = UploadedFile::fake()->image('voucher.jpg', 800, 800);
        }

        return $payload;
    }
}
