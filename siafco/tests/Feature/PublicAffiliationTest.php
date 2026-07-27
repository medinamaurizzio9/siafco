<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\DigitalCredential;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\CredentialService;
use App\Services\PublicAffiliationApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAffiliationTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): array
    {
        $sector = Sector::create(['name' => 'Salud', 'code' => 'SAL', 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'name' => 'Independiente', 'type' => 'independiente',
            'affiliation_fee' => 100, 'credential_fee' => 20,
            'currency' => 'BOB', 'is_active' => true,
        ]);
        return [$sector, $plan];
    }

    private function form(Sector $sector, AffiliationPlan $plan, array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Ana Pérez Lima', 'ci' => '7788991', 'issued_in' => 'LP',
            'phone' => '70000001', 'email' => 'ana@example.test', 'address' => 'Zona Central',
            'password' => 'clave-segura-123', 'password_confirmation' => 'clave-segura-123',
            'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id,
            'regional' => 'La Paz', 'institution' => 'Hospital Central', 'position' => 'Médica',
            'photo' => UploadedFile::fake()->image('foto.jpg'), 'birth_date' => '1990-05-10',
            'marital_status' => 'Soltera', 'terms' => '1', 'data_processing' => '1',
        ], $overrides);
    }

    public function test_registers_new_person_pending_affiliate_user_and_amount_snapshot(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $response = $this->post(route('public-affiliation.store'), $this->form($sector, $plan));

        $application = PublicAffiliationRequest::firstOrFail();
        $response->assertRedirect(route('public-affiliation.payment', $application));
        $this->assertDatabaseHas('people', ['ci' => '7788991']);
        $this->assertDatabaseHas('affiliates', ['ci' => '7788991', 'status' => 'pendiente_pago', 'registration_number' => null]);
        $this->assertDatabaseHas('users', ['email' => 'ana@example.test', 'role' => 'afiliado']);
        $this->assertSame('120.00', $application->amount_due);
        $this->assertSame('pending_payment', $application->status);
    }

    public function test_reuses_existing_investor_person_and_does_not_expose_private_data_in_status(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $person = Person::create(['full_name' => 'Nombre anterior', 'ci' => '7788991', 'email' => 'old@example.test']);

        $this->post(route('public-affiliation.store'), $this->form($sector, $plan))->assertRedirect();

        $this->assertSame(1, Person::where('ci', '7788991')->count());
        $application = PublicAffiliationRequest::firstOrFail();
        $this->assertSame($person->id, $application->person_id);
        $this->get(route('public-affiliation.status', $application))
            ->assertOk()->assertSee($application->request_code)
            ->assertDontSee('7788991')->assertDontSee('Zona Central');
    }

    public function test_submits_private_receipt_and_flags_duplicate_transaction_without_blocking(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$sector, $plan] = $this->catalog();
        $this->post(route('public-affiliation.store'), $this->form($sector, $plan));
        $application = PublicAffiliationRequest::firstOrFail();

        $this->post(route('public-affiliation.payment.store', $application), [
            'transaction_number' => 'TRX-001', 'payment_date' => today()->toDateString(),
            'payer_name' => 'Ana Pérez', 'paid_amount' => 120,
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect(route('public-affiliation.completed', $application));

        $payment = AffiliationPayment::firstOrFail();
        Storage::disk('local')->assertExists($payment->voucher_path);
        $this->assertSame('payment_submitted', $application->fresh()->status);
        $this->assertSame('pago_en_revision', $application->affiliate->fresh()->status);
        $this->assertSame('pending', $payment->status);
    }

    public function test_approval_is_idempotent_and_generates_registration_and_credential_once(): void
    {
        [$sector, $plan] = $this->catalog();
        $person = Person::create(['full_name' => 'Ana Pérez', 'ci' => '123', 'email' => 'ana@test.local']);
        $user = User::create(['person_id' => $person->id, 'name' => 'Ana', 'email' => 'ana@test.local', 'role' => 'afiliado', 'password' => Hash::make('secret123')]);
        $affiliate = Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => 'Ana Pérez', 'ci' => '123', 'email' => 'ana@test.local', 'status' => 'pago_en_revision']);
        $application = PublicAffiliationRequest::create(['person_id' => $person->id, 'affiliate_id' => $affiliate->id, 'user_id' => $user->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'public_token' => fake()->uuid(), 'request_code' => 'SOL-TEST-1', 'amount_due' => 120, 'status' => 'payment_submitted', 'submitted_at' => now()]);
        $payment = AffiliationPayment::create(['affiliate_id' => $affiliate->id, 'public_affiliation_request_id' => $application->id, 'affiliation_plan_id' => $plan->id, 'amount' => 120, 'paid_amount' => 120, 'expected_amount' => 120, 'transaction_number' => 'TRX-1', 'status' => 'pending']);
        $reviewer = User::create(['name' => 'Secretaría', 'email' => 'sec@test.local', 'role' => 'secretaria', 'password' => Hash::make('secret123')]);

        $credential = $this->mock(CredentialService::class);
        $credential->shouldReceive('generate')->once()->andReturn(new DigitalCredential());
        $service = new PublicAffiliationApprovalService($credential);
        $service->approve($payment, $reviewer->id);

        $this->assertSame('SAL-000001', $affiliate->fresh()->registration_number);
        $this->assertSame('activo', $affiliate->fresh()->status);
        $this->assertSame('approved', $application->fresh()->status);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->approve($payment->fresh(), $reviewer->id);
    }
}
