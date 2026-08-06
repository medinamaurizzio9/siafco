<?php

namespace Tests\Feature;

use App\Events\PaymentConfirmed;
use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\DigitalCredential;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\CredentialService;
use App\Services\PaymentBalanceService;
use App\Services\PaymentLifecycleService;
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

        $this->actingAs($secretary)->post(route('payments.confirm', $payment))->assertRedirect();
        $payment->refresh();
        $this->assertSame('confirmed', $payment->status);
        $this->assertSame($secretary->id, $payment->confirmed_by);
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

        $this->actingAs($secretary)->post(route('payments.confirm', $payment))->assertRedirect();
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

        $this->actingAs($secretary)->post(route('payments.confirm', $payment))
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

        $this->actingAs($secretary)->post(route('payments.reject', $payment), [
            'rejection_reason' => 'COMPROBANTE ILEGIBLE',
        ])->assertRedirect();
        $payment->refresh();
        $this->assertSame('rejected', $payment->status);
        $this->assertSame($secretary->id, $payment->rejected_by);
        Storage::disk('local')->assertExists($payment->voucher_path);

        $second = $payment->replicate(['rejection_reason', 'rejected_by', 'rejected_at']);
        $second->status = 'pending';
        $second->reference_number = 'REF-CONFIRM';
        $second->save();

        $this->actingAs($cashier)->post(route('payments.confirm', $second))->assertRedirect();
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
        $this->actingAs($secretary)->get(route('payments.receipt.download', $payment))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
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
        return array_merge([
            'affiliate_id' => $affiliate->id,
            'amount' => '120.00',
            'currency' => 'BOB',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'payment_method' => 'transferencia',
            'bank_name' => 'BANCO TEST',
            'reference_number' => 'REF-001',
            'transaction_number' => 'TRX-001',
            'observations' => 'PAGO MANUAL',
            'status' => 'pending',
        ], $overrides);
    }
}
