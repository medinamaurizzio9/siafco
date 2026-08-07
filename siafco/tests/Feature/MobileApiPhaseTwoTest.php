<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\InstitutionalSetting;
use App\Models\MobileApiIdempotencyKey;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\PublicAffiliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        InstitutionalSetting::clearCurrentCache();
    }

    public function test_public_catalogs_return_only_active_options(): void
    {
        [$sector, $plan] = $this->catalog();
        Sector::create(['name' => 'Inactivo', 'code' => 'INA', 'is_active' => false]);
        AffiliationPlan::create(['name' => 'Plan inactivo', 'affiliation_fee' => 1, 'credential_fee' => 1, 'is_active' => false]);

        $response = $this->getJson('/api/mobile/v1/catalogs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sectors.0.id', $sector->id)
            ->assertJsonPath('data.plans.0.id', $plan->id)
            ->assertJsonMissing(['name' => 'Inactivo'])
            ->assertJsonMissing(['name' => 'Plan inactivo'])
            ->assertJsonStructure(['data' => ['issued_in', 'regionals', 'marital_statuses', 'institution']]);
    }

    public function test_public_registration_creates_affiliate_request_and_returns_one_token(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $response = $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan), [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.profile.user.email', 'ana@example.test')
            ->assertJsonPath('data.profile.affiliate.status', 'pendiente_pago')
            ->assertJsonPath('data.affiliation_request.status', 'pending_payment')
            ->assertJsonMissingPath('data.affiliation_request.public_token')
            ->assertJsonMissingPath('data.affiliation_request.verification_token');

        $user = User::where('email', 'ana@example.test')->firstOrFail();
        $this->assertSame('affiliate', $user->user_type);
        $this->assertDatabaseHas('public_affiliation_requests', [
            'id' => PublicAffiliationRequest::firstOrFail()->id,
            'terms_version' => config('siafco.terms_version', '2026.1'),
            'privacy_version' => config('siafco.privacy_version', '2026.1'),
            'acceptance_ip' => '127.0.0.1',
        ]);
        $this->assertNotNull(PublicAffiliationRequest::firstOrFail()->terms_accepted_at);
        $this->assertNotNull(PublicAffiliationRequest::firstOrFail()->privacy_accepted_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull(PersonalAccessToken::findToken($response->json('data.access_token')));
        $this->withToken($response->json('data.access_token'))
            ->getJson('/api/mobile/v1/me/affiliation-request')
            ->assertOk()
            ->assertJsonPath('data.affiliation_request.request_code', PublicAffiliationRequest::firstOrFail()->request_code);
    }

    public function test_registration_never_reuses_internal_or_existing_affiliate_users(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $internal = User::factory()->create([
            'email' => 'internal-owner@example.test',
            'role' => 'administrador',
            'user_type' => 'internal',
            'password' => Hash::make('Internal1234'),
        ]);

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'email' => $internal->email,
        ]), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonMissingPath('data.access_token');

        $this->assertSame('internal', $internal->fresh()->user_type);
        $this->assertTrue(Hash::check('Internal1234', $internal->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        [$affiliateUser] = $this->application('pending_payment', 'pendiente_pago', 'existing-affiliate@example.test', '7799001');
        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'ci' => '7799001',
            'email' => $affiliateUser->email,
            'photo' => UploadedFile::fake()->image('existing.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonMissingPath('data.access_token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_rejects_unsafe_existing_person_and_duplicate_email_without_token(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        Person::create([
            'full_name' => 'Nombre Original',
            'ci' => '4455667',
            'email' => 'original@example.test',
            'birth_date' => '1980-01-01',
        ]);
        User::factory()->create([
            'email' => 'taken-email@example.test',
            'role' => 'afiliado',
            'user_type' => 'affiliate',
        ]);

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'ci' => '4455667',
            'email' => 'new-owner@example.test',
            'photo' => UploadedFile::fake()->image('unsafe.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonMissingPath('data.access_token');

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'ci' => '4455668',
            'email' => 'taken-email@example.test',
            'photo' => UploadedFile::fake()->image('taken.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonMissingPath('data.access_token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_equivalent_duplicate_registration_is_rejected_after_first_success(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan), [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'photo' => UploadedFile::fake()->image('repeat.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(409);

        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('affiliates', 1);
        $this->assertDatabaseCount('public_affiliation_requests', 1);
    }

    public function test_registration_rejects_duplicates_inactive_catalog_invalid_photo_terms_and_admin_fields(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan), [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'email' => 'otra@example.test',
            'photo' => UploadedFile::fake()->image('otra.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ya existe una cuenta o solicitud asociada. Inicia sesión para continuar.');

        $inactivePlan = AffiliationPlan::create(['name' => 'Cerrado', 'affiliation_fee' => 1, 'credential_fee' => 1, 'is_active' => false]);
        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $inactivePlan, [
            'ci' => '8899002',
            'email' => 'inactive@example.test',
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['affiliation_plan_id']]);

        $otherSector = Sector::create(['name' => 'Otro sector', 'code' => 'OTR', 'is_active' => true]);
        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($otherSector, $plan, [
            'ci' => '8899006',
            'email' => 'mismatch@example.test',
            'photo' => UploadedFile::fake()->image('mismatch.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['affiliation_plan_id']]);

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'ci' => '8899003',
            'email' => 'bad-photo@example.test',
            'photo' => UploadedFile::fake()->createWithContent('photo.jpg', 'not-an-image'),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['photo']]);

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'ci' => '8899004',
            'email' => 'terms@example.test',
            'terms_accepted' => false,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['terms_accepted']]);

        $this->post('/api/mobile/v1/affiliation-requests', $this->registrationPayload($sector, $plan, [
            'ci' => '8899005',
            'email' => 'admin-field@example.test',
            'status' => 'approved',
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_public_registration_rate_limit_returns_uniform_json(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.'.$attempt])
                ->postJson('/api/mobile/v1/affiliation-requests', [])
                ->assertUnprocessable();
        }

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.20.40.40'])
                ->postJson('/api/mobile/v1/affiliation-requests', ['attempt' => $attempt])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.40.40'])
            ->postJson('/api/mobile/v1/affiliation-requests', [])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_affiliate_sees_only_own_request_and_requires_valid_token(): void
    {
        [$firstUser, $firstRequest] = $this->application();
        [, $secondRequest] = $this->application('payment_submitted', 'pago_en_revision', 'second@example.test', '7788002');
        $firstToken = $this->tokenFor($firstUser);

        $this->getJson('/api/mobile/v1/me/affiliation-request')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->getJson('/api/mobile/v1/me/affiliation-request')
            ->assertOk()
            ->assertJsonPath('data.affiliation_request.request_code', $firstRequest->request_code)
            ->assertJsonMissing(['request_code' => $secondRequest->request_code]);
    }

    public function test_affiliation_request_exposes_configured_payment_qr_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('institutional/payment/payment-qr.png', 'qr-image');
        $setting = InstitutionalSetting::current();
        $setting->update([
            'payment_qr_path' => 'institutional/payment/payment-qr.png',
            'payment_bank' => 'Banco Mercantil',
            'payment_holder' => 'Cooperativa Tierra Bendita',
            'payment_account' => '123456789',
            'phone' => '70000000',
        ]);
        InstitutionalSetting::clearCurrentCache();
        [$user] = $this->application();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/mobile/v1/me/affiliation-request')
            ->assertOk()
            ->assertJsonPath('data.affiliation_request.payment_instructions.bank', 'Banco Mercantil')
            ->assertJsonPath('data.affiliation_request.payment_instructions.holder', 'Cooperativa Tierra Bendita')
            ->assertJsonPath('data.affiliation_request.payment_instructions.account', '123456789')
            ->assertJsonPath('data.affiliation_request.payment_instructions.support_phone', '70000000')
            ->assertJsonPath(
                'data.affiliation_request.payment_instructions.qr_url',
                InstitutionalSetting::current()->paymentQrUrl()
            );
    }

    public function test_affiliation_request_keeps_payment_qr_url_nullable_when_missing(): void
    {
        Storage::fake('public');
        $setting = InstitutionalSetting::current();
        $setting->update([
            'payment_qr_path' => 'institutional/payment/missing.png',
            'payment_bank' => 'Banco Mercantil',
            'phone' => null,
        ]);
        InstitutionalSetting::clearCurrentCache();
        [$user] = $this->application();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/mobile/v1/me/affiliation-request')
            ->assertOk()
            ->assertJsonPath('data.affiliation_request.payment_instructions.bank', 'Banco Mercantil')
            ->assertJsonPath('data.affiliation_request.payment_instructions.qr_url', null)
            ->assertJsonPath('data.affiliation_request.payment_instructions.support_phone', null);
    }

    public function test_blocked_or_internal_token_cannot_access_affiliation_request(): void
    {
        [$user] = $this->application('pending_payment', 'suspendido');
        $internal = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/mobile/v1/me/affiliation-request')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->withToken($this->tokenFor($internal))
            ->getJson('/api/mobile/v1/me/affiliation-request')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_payment_submission_stores_private_receipt_and_updates_statuses(): void
    {
        Storage::fake('local');
        [$user, $application] = $this->application();

        $response = $this->withToken($this->tokenFor($user))
            ->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload(), [
                'Accept' => 'application/json',
                'Idempotency-Key' => 'pay-001',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.idempotent', false)
            ->assertJsonPath('data.affiliation_request.status', 'payment_submitted')
            ->assertJsonPath('data.affiliation_request.payment.status', 'pending')
            ->assertJsonPath('data.affiliation_request.payment.has_receipt', true)
            ->assertJsonPath('data.affiliation_request.capabilities.can_submit_payment', false);

        $payment = AffiliationPayment::firstOrFail();
        Storage::disk('local')->assertExists($payment->voucher_path);
        Storage::disk('public')->assertMissing($payment->voucher_path);
        $this->assertSame('payment_submitted', $application->fresh()->status);
        $this->assertSame('pago_en_revision', $application->affiliate->fresh()->status);
    }

    public function test_payment_idempotency_reuses_original_result_without_creating_duplicates(): void
    {
        Storage::fake('local');
        [$user] = $this->application();
        $token = $this->tokenFor($user);

        $first = $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload(), [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'same-payment',
        ])->assertOk()->assertJsonPath('data.idempotent', false);

        $second = $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 600, 600),
        ]), [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'same-payment',
        ])->assertOk()->assertJsonPath('data.idempotent', false);

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('affiliation_payments', 1);
        $this->assertDatabaseCount('mobile_api_idempotency_keys', 1);
        $this->assertSame('TRX-001', AffiliationPayment::firstOrFail()->transaction_number);
        $this->assertSame('completed', MobileApiIdempotencyKey::firstOrFail()->status);
    }

    public function test_same_idempotency_key_with_different_payload_or_file_is_rejected(): void
    {
        Storage::fake('local');
        [$user] = $this->application();
        $token = $this->tokenFor($user);

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload(), [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'strict-key',
        ])->assertOk();

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'transaction_number' => 'TRX-CHANGED',
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 600, 600),
        ]), [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'strict-key',
        ])->assertStatus(409);

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'receipt' => UploadedFile::fake()->image('other-receipt.jpg', 700, 700),
        ]), [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'strict-key',
        ])->assertStatus(409);

        $this->assertDatabaseCount('affiliation_payments', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('affiliation-receipts'));
    }

    public function test_same_idempotency_key_is_scoped_per_user(): void
    {
        Storage::fake('local');
        [$firstUser] = $this->application();
        [$secondUser] = $this->application('pending_payment', 'pendiente_pago', 'second-pay@example.test', '7788123');

        Sanctum::actingAs($firstUser, ['mobile']);
        $this->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'receipt' => UploadedFile::fake()->image('first.jpg'),
        ]), [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'shared-key',
        ])->assertOk();

        Sanctum::actingAs($secondUser, ['mobile']);
        $this->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'transaction_number' => 'TRX-002',
            'receipt' => UploadedFile::fake()->image('second.jpg'),
        ]), [
            'Accept' => 'application/json',
            'Idempotency-Key' => 'shared-key',
        ])->assertOk();

        $this->assertDatabaseCount('affiliation_payments', 2);
        $this->assertDatabaseCount('mobile_api_idempotency_keys', 2);
    }

    public function test_payment_rejects_invalid_amount_status_key_file_and_admin_fields(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('local');
        [$user, $application] = $this->application();
        $token = $this->tokenFor($user);

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'paid_amount' => 0,
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['paid_amount']]);

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'paid_amount' => '1e3',
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['paid_amount']]);

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'receipt' => UploadedFile::fake()->createWithContent('receipt.pdf', 'not-a-pdf'),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['receipt']]);

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'status' => 'approved',
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);

        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload(), [
            'Accept' => 'application/json',
            'Idempotency-Key' => str_repeat('x', 201),
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['Idempotency-Key']]);

        $application->update(['status' => 'approved']);
        $this->withToken($token)->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
            'receipt' => UploadedFile::fake()->image('receipt-again.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_payment_keeps_decimal_amount_exact_and_expected_amount_from_request_plan(): void
    {
        Storage::fake('local');
        [$user] = $this->application();

        $this->withToken($this->tokenFor($user))
            ->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload([
                'paid_amount' => '120.35',
            ]), ['Accept' => 'application/json'])
            ->assertOk();

        $payment = AffiliationPayment::firstOrFail();
        $this->assertSame('120.35', number_format((float) $payment->paid_amount, 2, '.', ''));
        $this->assertSame('120.00', number_format((float) $payment->expected_amount, 2, '.', ''));
    }

    public function test_payment_cleans_private_receipt_when_service_rejects_transaction(): void
    {
        Storage::fake('local');
        [$user] = $this->application();
        $this->app->bind(PublicAffiliationService::class, fn () => new class extends PublicAffiliationService {
            public function submitPayment(PublicAffiliationRequest $request, array $data, ?string $receiptPath): AffiliationPayment
            {
                throw ValidationException::withMessages(['transaction_number' => 'Pago rechazado por prueba.']);
            }
        });

        $this->withToken($this->tokenFor($user))
            ->post('/api/mobile/v1/me/affiliation-request/payment', $this->paymentPayload(), [
                'Accept' => 'application/json',
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame([], Storage::disk('local')->allFiles('affiliation-receipts'));
        $this->assertDatabaseCount('mobile_api_idempotency_keys', 0);
    }

    public function test_payment_rate_limit_returns_uniform_json(): void
    {
        [$user] = $this->application();
        $token = $this->tokenFor($user);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.50.60.60'])
                ->withToken($token)
                ->postJson('/api/mobile/v1/me/affiliation-request/payment', [])
                ->assertUnprocessable();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.50.60.60'])
            ->withToken($token)
            ->postJson('/api/mobile/v1/me/affiliation-request/payment', [])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_web_public_affiliation_flow_still_uses_shared_validation(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $response = $this->post(route('public-affiliation.store'), [
            'full_name' => 'Ana Perez Lima',
            'ci' => '9911223',
            'issued_in' => 'LP',
            'phone' => '70000001',
            'email' => 'web-mobile@example.test',
            'address' => 'Zona Central',
            'password' => 'clave-segura-123',
            'password_confirmation' => 'clave-segura-123',
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'regional' => 'LA PAZ',
            'institution' => 'Hospital Central',
            'position' => 'Medica',
            'photo' => UploadedFile::fake()->image('foto.jpg'),
            'birth_date' => '1990-05-10',
            'marital_status' => 'SOLTERO',
            'terms' => '1',
            'data_processing' => '1',
        ]);

        $application = PublicAffiliationRequest::firstOrFail();
        $response->assertRedirect(route('public-affiliation.payment', $application));
        $this->assertDatabaseHas('users', ['email' => 'web-mobile@example.test', 'user_type' => 'affiliate']);
    }

    private function catalog(): array
    {
        $sector = Sector::create([
            'name' => 'Salud',
            'code' => 'SAL-'.Str::upper(Str::random(5)),
            'regional' => 'LA PAZ',
            'institution' => 'Hospital Central',
            'is_active' => true,
        ]);

        $plan = AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'Independiente',
            'type' => 'independiente',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'currency' => 'BOB',
            'payment_instructions' => 'Transferencia bancaria.',
            'is_active' => true,
        ]);

        return [$sector, $plan];
    }

    private function registrationPayload(Sector $sector, AffiliationPlan $plan, array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Ana Perez Lima',
            'ci' => '7788991',
            'ci_complement' => null,
            'issued_in' => 'LP',
            'phone' => '70000001',
            'email' => 'ana@example.test',
            'address' => 'Zona Central',
            'password' => 'clave-segura-123',
            'password_confirmation' => 'clave-segura-123',
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'regional' => 'LA PAZ',
            'institution' => 'Hospital Central',
            'position' => 'Medica',
            'photo' => UploadedFile::fake()->image('foto.jpg', 700, 700),
            'birth_date' => '1990-05-10',
            'marital_status' => 'SOLTERO',
            'terms_accepted' => '1',
            'privacy_accepted' => '1',
            'device_name' => 'Pixel 8',
        ], $overrides);
    }

    private function application(
        string $requestStatus = 'pending_payment',
        string $affiliateStatus = 'pendiente_pago',
        string $email = 'ana@example.test',
        string $ci = '7788001'
    ): array {
        [$sector, $plan] = $this->catalog();
        $person = Person::create([
            'full_name' => 'Ana Perez',
            'ci' => $ci,
            'issued_in' => 'LP',
            'phone' => '70000001',
            'email' => $email,
            'address' => 'Zona Central',
            'birth_date' => '1990-05-10',
            'marital_status' => 'SOLTERO',
        ]);
        $user = User::create([
            'person_id' => $person->id,
            'name' => 'Ana Perez',
            'email' => $email,
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'is_active' => true,
            'password' => Hash::make('Secret1234'),
        ]);
        $affiliate = Affiliate::create([
            'person_id' => $person->id,
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'Ana Perez',
            'ci' => $ci,
            'phone' => '70000001',
            'email' => $email,
            'address' => 'Zona Central',
            'regional' => 'LA PAZ',
            'institution' => 'Hospital Central',
            'position' => 'Medica',
            'birth_date' => '1990-05-10',
            'marital_status' => 'SOLTERO',
            'status' => $affiliateStatus,
        ]);
        $application = PublicAffiliationRequest::create([
            'person_id' => $person->id,
            'affiliate_id' => $affiliate->id,
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'public_token' => (string) Str::uuid(),
            'request_code' => 'SOL-'.Str::upper(Str::random(8)),
            'amount_due' => $plan->total_amount,
            'status' => $requestStatus,
            'submitted_at' => now(),
        ]);

        return [$user->fresh('affiliate'), $application->fresh('affiliate', 'plan')];
    }

    private function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'transaction_number' => 'TRX-001',
            'payment_date' => today()->toDateString(),
            'bank_name' => 'Banco Union',
            'payer_name' => 'Ana Perez',
            'paid_amount' => 120,
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 600, 600),
            'observations' => 'Pago desde Android',
        ], $overrides);
    }

    private function tokenFor(User $user, string $name = 'android'): string
    {
        return $user->createToken($name, ['mobile'])->plainTextToken;
    }
}
