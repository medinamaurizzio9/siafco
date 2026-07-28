<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateBenefit;
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
            'regional' => 'LA PAZ', 'institution' => 'Hospital Central', 'position' => 'Médica',
            'photo' => UploadedFile::fake()->image('foto.jpg'), 'birth_date' => '1990-05-10',
            'marital_status' => 'SOLTERO', 'terms' => '1', 'data_processing' => '1',
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
        $this->assertDatabaseHas('people', [
            'ci' => '7788991', 'full_name' => 'ANA PÉREZ LIMA', 'address' => 'ZONA CENTRAL',
            'email' => 'ana@example.test',
        ]);
        $this->assertDatabaseHas('affiliates', ['ci' => '7788991', 'status' => 'pendiente_pago', 'registration_number' => null]);
        $this->assertDatabaseHas('users', ['email' => 'ana@example.test', 'role' => 'afiliado']);
        $this->assertTrue(Hash::check('clave-segura-123', User::where('email', 'ana@example.test')->firstOrFail()->password));
        $this->assertSame('120.00', $application->amount_due);
        $this->assertSame('pending_payment', $application->status);
        $photoPath = Affiliate::firstOrFail()->photo_path;
        $this->assertMatchesRegularExpression('/^affiliates\/photos\/[0-9a-f-]{36}\.jpg$/', $photoPath);
        [$width, $height] = getimagesize(Storage::disk('public')->path($photoPath));
        $this->assertSame([600, 600], [$width, $height]);
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

        $this->get(route('public-affiliation.status', $application))
            ->assertOk()
            ->assertDontSee('PAYMENT SUBMITTED')
            ->assertDontSee('payment_submitted')
            ->assertSee('Pago enviado para revisión')
            ->assertSee('Hemos recibido la información de tu pago.')
            ->assertSee('Paso 3 de 4')
            ->assertSee('bg-orange-100', false);
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

    public function test_password_component_is_reusable_and_access_password_is_shown_once(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$sector, $plan] = $this->catalog();

        $this->get(route('login'))->assertOk()
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-pressed="false"', false);

        $this->post(route('public-affiliation.store'), $this->form($sector, $plan))->assertRedirect();
        $application = PublicAffiliationRequest::firstOrFail();
        $this->get(route('public-affiliation.payment', $application))->assertOk();
        $this->post(route('public-affiliation.payment.store', $application), [
            'transaction_number' => 'Mixta-AbC-01',
            'payment_date' => today()->toDateString(),
            'payer_name' => 'Ana Pérez',
            'paid_amount' => 120,
        ])->assertRedirect(route('public-affiliation.completed', $application));

        $this->get(route('public-affiliation.completed', $application))
            ->assertOk()->assertSee('clave-segura-123')->assertSee('Copiar contraseña');
        $this->get(route('public-affiliation.completed', $application))
            ->assertOk()->assertDontSee('clave-segura-123')
            ->assertSee('Por seguridad, la contraseña solo se muestra una vez.');
        $this->assertDatabaseHas('affiliation_payments', ['transaction_number' => 'Mixta-AbC-01', 'payer_name' => 'ANA PÉREZ']);
    }

    public function test_pending_user_can_login_but_only_sees_tracking_and_cannot_download_credential(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $this->post(route('public-affiliation.store'), $this->form($sector, $plan));
        $application = PublicAffiliationRequest::firstOrFail();
        AffiliateBenefit::create(['title' => 'CREDENCIAL DIGITAL', 'icon' => 'card', 'active' => true, 'visible_when_pending' => true, 'order' => 1]);

        $this->post(route('login.post'), ['email' => 'ana@example.test', 'password' => 'clave-segura-123'])
            ->assertRedirect(route('affiliate.panel'));
        $this->get(route('affiliate.panel'))->assertOk()
            ->assertSee($application->request_code)
            ->assertSee('Pendiente de pago')
            ->assertDontSee('pending_payment')
            ->assertSee('Bloqueado')
            ->assertDontSee('Descargar PDF');
        $this->get(route('affiliate.credential.pdf'))->assertRedirect(route('affiliate.panel'));
        $this->get(route('affiliate.credential.png'))->assertRedirect(route('affiliate.panel'));
        $this->get(route('investments.panel'))->assertRedirect(route('affiliate.panel'));
    }

    public function test_active_user_sees_full_panel_but_cannot_download_existing_credential(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $person = Person::create(['full_name' => 'ANA', 'ci' => 'ACT-1', 'email' => 'active@test.local']);
        $user = User::create(['person_id' => $person->id, 'name' => 'ANA', 'email' => 'active@test.local', 'role' => 'afiliado', 'password' => Hash::make('secret123')]);
        $affiliate = Affiliate::create([
            'person_id' => $person->id, 'user_id' => $user->id, 'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id, 'full_name' => 'ANA', 'ci' => 'ACT-1',
            'email' => 'active@test.local', 'status' => 'activo',
            'registration_number' => 'SAL-000001', 'verification_token' => fake()->uuid(),
        ]);
        foreach (['credentials/card.pdf', 'credentials/card.png', 'credentials/qr.png'] as $path) Storage::disk('public')->put($path, 'file');
        DigitalCredential::create([
            'affiliate_id' => $affiliate->id, 'pdf_path' => 'credentials/card.pdf',
            'png_path' => 'credentials/card.png', 'qr_path' => 'credentials/qr.png',
            'generated_at' => now()->addMinute(),
        ]);
        AffiliateBenefit::create(['title' => 'SOPORTE', 'icon' => 'support', 'active' => true, 'visible_when_pending' => true, 'order' => 1]);

        $this->actingAs($user)->get(route('affiliate.panel'))->assertOk()
            ->assertSee('VER MI CREDENCIAL')->assertDontSee('Descargar PDF')
            ->assertDontSee('Descargar PNG')->assertDontSee('Ver e imprimir')
            ->assertSee('Mis servicios y beneficios')
            ->assertDontSee('Bloqueado');
        $this->actingAs($user)->get(route('affiliate.credential.preview'))->assertOk()
            ->assertSee('MI CREDENCIAL')->assertDontSee('Descargar PDF')
            ->assertDontSee('Descargar PNG')->assertDontSee('Imprimir credencial');
        $this->actingAs($user)->get(route('affiliate.credential.pdf'))->assertForbidden();
        $this->actingAs($user)->get(route('affiliate.credential.png'))->assertForbidden();
    }

    public function test_administrative_public_requests_show_translated_statuses(): void
    {
        [$sector, $plan] = $this->catalog();
        $person = Person::create(['full_name' => 'ANA', 'ci' => 'ADM-1', 'email' => 'admin-request@test.local']);
        PublicAffiliationRequest::create([
            'person_id' => $person->id, 'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id, 'public_token' => fake()->uuid(),
            'request_code' => 'SOL-ADMIN-1', 'amount_due' => 120,
            'status' => 'under_review', 'submitted_at' => now(),
        ]);
        $secretary = User::create([
            'name' => 'SECRETARÍA', 'email' => 'secretary@test.local',
            'role' => 'secretaria', 'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($secretary)->get(route('public-affiliation.admin.index'))
            ->assertOk()->assertSee('Pago en revisión')->assertDontSee('UNDER REVIEW');
    }

    public function test_public_form_shows_closed_select_catalogs_and_accessibility(): void
    {
        $this->catalog();

        $response = $this->get(route('public-affiliation.create'))->assertOk();
        foreach (['LP - La Paz', 'CB - Cochabamba', 'SC - Santa Cruz', 'BN - Beni', 'PA - Pando', 'TR - Tarija', 'CH - Chuquisaca', 'OR - Oruro', 'PT - Potosí'] as $option) {
            $response->assertSee($option);
        }
        foreach (['Soltero', 'Casado', 'Divorciado', 'Viudo'] as $option) {
            $response->assertSee($option);
        }
        foreach (['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSÍ', 'SUCRE', 'TARIJA', 'BENI', 'PANDO'] as $value) {
            $response->assertSee('value="'.$value.'"', false);
        }
        $response->assertSee('aria-required="true"', false)
            ->assertSee('aria-describedby="issued_in-error"', false)
            ->assertSee('data-photo-cropper', false);
    }

    public function test_closed_selects_reject_unknown_values_and_keep_old_input(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $response = $this->from(route('public-affiliation.create'))->post(
            route('public-affiliation.store'),
            $this->form($sector, $plan, [
                'issued_in' => 'XX', 'marital_status' => 'CONVIVIENTE', 'regional' => 'OTRA',
            ])
        );

        $response->assertRedirect(route('public-affiliation.create'))
            ->assertSessionHasErrors(['issued_in', 'marital_status', 'regional'])
            ->assertSessionHasInput('issued_in', 'XX')
            ->assertSessionHasInput('marital_status', 'CONVIVIENTE')
            ->assertSessionHasInput('regional', 'OTRA');
    }

    public function test_ci_complement_remains_optional_and_passwords_must_match(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $this->post(route('public-affiliation.store'), $this->form($sector, $plan, ['ci_complement' => null]))
            ->assertRedirect();
        $this->assertDatabaseHas('people', ['ci' => '7788991', 'ci_complement' => null]);

        $this->post(route('public-affiliation.store'), $this->form($sector, $plan, [
            'ci' => '7788992', 'email' => 'otra@example.test',
            'password_confirmation' => 'distinta-123',
        ]))->assertSessionHasErrors(['password']);
    }

    public function test_invalid_photo_formats_and_oversized_files_are_rejected_in_spanish(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        $this->post(route('public-affiliation.store'), $this->form($sector, $plan, [
            'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('photo');
        $this->post(route('public-affiliation.store'), $this->form($sector, $plan, [
            'photo' => UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors('photo');
        $invalidImageResponse = $this->post(route('public-affiliation.store'), $this->form($sector, $plan, [
            'photo' => UploadedFile::fake()->createWithContent('falsa.jpg', 'not-an-image'),
        ]));
        $invalidImageResponse->assertRedirect()->assertSessionHasErrors('photo');

        $this->post(route('public-affiliation.store'), $this->form($sector, $plan, [
            'photo' => UploadedFile::fake()->create('grande.jpg', 5121, 'image/jpeg'),
        ]))->assertSessionHasErrors(['photo' => 'La fotografía supera el tamaño permitido de 5 MB.']);
    }
}
