<?php

namespace Tests\Feature;

use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\Affiliate;
use App\Models\Person;
use App\Models\PublicAffiliationRequest;
use App\Models\Sector;
use App\Models\User;
use App\Services\PaymentBalanceService;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpressAffiliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_cta_points_to_express_affiliation_and_old_form_remains_available(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Crear afiliación')
            ->assertSee(route('public-affiliation.express.create'), false);

        $this->get(route('public-affiliation.create'))->assertOk();
    }

    public function test_express_form_is_public_and_validates_required_fields(): void
    {
        $this->get(route('public-affiliation.express.create'))
            ->assertOk()
            ->assertSee('Crear afiliación')
            ->assertSee('Realiza tu pago');

        $this->post(route('public-affiliation.express.store'), [])
            ->assertSessionHasErrors(['full_name', 'ci', 'issued_in', 'phone', 'sector_id', 'affiliation_plan_id', 'transaction_number', 'bank_name', 'payer_name', 'payment_date']);
    }

    public function test_express_form_keeps_real_payment_fields_enabled_until_native_submit(): void
    {
        $html = $this->get(route('public-affiliation.express.create'))
            ->assertOk()
            ->getContent();

        foreach (['full_name', 'ci', 'issued_in', 'phone', 'sector_id', 'affiliation_plan_id', 'transaction_number', 'bank_name', 'payer_name', 'payment_date', 'receipt', 'observations'] as $name) {
            $this->assertMatchesRegularExpression('/<(?:input|select|textarea)\b(?=[^>]*\bname="'.preg_quote($name, '/').'"[\s>])(?![^>]*\bdisabled\b)[^>]*>/s', $html);
        }

        $this->assertStringContainsString("form.addEventListener('submit'", $html);
        $this->assertStringContainsString("form.addEventListener('input', syncSummary)", $html);
        $this->assertStringNotContainsString("data-submit-once]')?.addEventListener('click'", $html);
    }

    public function test_reported_manual_express_payload_creates_application_payment_and_affiliate(): void
    {
        $sector = Sector::create(['name' => 'Magisterio Rural', 'code' => 'MAG', 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'AFILIACION CONVENIO',
            'affiliation_fee' => 255,
            'credential_fee' => 50,
            'is_active' => true,
        ]);

        $response = $this->post(route('public-affiliation.express.store'), [
            'full_name' => 'MEDINA GONZALES PRUEBA7',
            'ci' => '4995153055',
            'issued_in' => 'LP',
            'phone' => '75865765',
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'transaction_number' => '5551516845313',
            'bank_name' => 'ACONOMICO',
            'payer_name' => 'MAURIZZIO MEDINA HH',
            'payment_date' => '2026-09-16',
        ]);

        $application = PublicAffiliationRequest::with('payment', 'affiliate', 'person')->firstOrFail();
        $response->assertRedirect(route('public-affiliation.express.completed', $application));

        $this->assertSame('MEDINA GONZALES PRUEBA7', $application->person->full_name);
        $this->assertSame('4995153055', $application->affiliate->ci);
        $this->assertSame('5551516845313', $application->payment->transaction_number);
        $this->assertSame('ACONOMICO', $application->payment->bank_name);
        $this->assertSame(305.0, (float) $application->payment->paid_amount);
    }

    public function test_express_creates_existing_entities_with_synthetic_access_without_activating_affiliate(): void
    {
        Storage::fake('local');
        [$sector, $plan] = $this->catalog();

        $response = $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan, [
            'receipt' => UploadedFile::fake()->image('voucher.jpg', 600, 600),
        ]));

        $application = PublicAffiliationRequest::with('affiliate', 'payment', 'user', 'person')->firstOrFail();
        $response->assertRedirect(route('public-affiliation.express.completed', $application));

        $this->assertStringStartsWith('SOL-', $application->request_code);
        $this->assertSame('payment_submitted', $application->status);
        $this->assertSame('pago_en_revision', $application->affiliate->status);
        $this->assertNull($application->affiliate->registration_number);
        $this->assertSame('567864933@siafco.com', $application->user->email);
        $this->assertTrue($application->user->must_change_password);
        $this->assertTrue(Hash::check('567864933', $application->user->password));
        $this->assertSame($plan->total_amount, (float) $application->payment->expected_amount);
        $this->assertSame($plan->total_amount, (float) $application->payment->paid_amount);
        $this->assertSame(PaymentStatus::UNDER_REVIEW, $application->payment->status);
        $this->assertSame('web', $application->payment->source);
        Storage::disk('local')->assertExists($application->payment->voucher_path);

        $this->get(route('public-affiliation.express.completed', $application))
            ->assertOk()
            ->assertSee('Afiliación registrada')
            ->assertSee('Cuenta de acceso creada')
            ->assertSee('567864933@siafco.com')
            ->assertSee('567864933');
    }

    public function test_express_rejects_incompatible_plan_future_date_invalid_phone_and_duplicate_ci(): void
    {
        [$sector, $plan] = $this->catalog();
        [$otherSector] = $this->catalog('OTR');

        $this->post(route('public-affiliation.express.store'), $this->payload($otherSector, $plan))
            ->assertSessionHasErrors('affiliation_plan_id');

        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan, ['payment_date' => now()->addDay()->format('Y-m-d')]))
            ->assertSessionHasErrors('payment_date');

        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan, ['phone' => '123']))
            ->assertSessionHasErrors('phone');

        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan))->assertRedirect();
        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan, ['phone' => '76543211']))
            ->assertSessionHasErrors('ci');

        $this->assertSame(1, Affiliate::where('ci', '567864933')->count());
        $this->assertSame(1, PublicAffiliationRequest::count());
        $this->assertSame(1, User::where('email', '567864933@siafco.com')->count());
    }

    public function test_existing_user_collision_does_not_create_second_user(): void
    {
        [$sector, $plan] = $this->catalog();
        User::create([
            'name' => 'Existente',
            'email' => '567864933@siafco.com',
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan))
            ->assertSessionHasErrors('ci');

        $this->assertSame(1, User::where('email', '567864933@siafco.com')->count());
        $this->assertSame(0, PublicAffiliationRequest::count());
    }

    public function test_express_allows_same_phone_for_different_ci_accounts(): void
    {
        [$sector, $plan] = $this->catalog();

        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan, [
            'ci' => '4995153055',
            'phone' => '75865765',
            'transaction_number' => 'TRX-PHONE-A',
        ]))->assertRedirect();

        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan, [
            'full_name' => 'OTRA PERSONA',
            'ci' => '567864966',
            'phone' => '75865765',
            'transaction_number' => 'TRX-PHONE-B',
        ]))->assertRedirect();

        $this->assertSame(2, PublicAffiliationRequest::count());
        $this->assertSame(1, User::where('email', '4995153055@siafco.com')->count());
        $this->assertSame(1, User::where('email', '567864966@siafco.com')->count());
    }

    public function test_first_login_forces_password_then_profile_completion_before_panel(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan));

        $this->post(route('login.post'), ['email' => '567864933@siafco.com', 'password' => '567864933'])
            ->assertRedirect(route('password.force.edit'));

        $user = User::where('email', '567864933@siafco.com')->firstOrFail();
        $this->actingAs($user)->get(route('affiliate.panel'))->assertRedirect(route('password.force.edit'));

        $this->actingAs($user)->patch(route('password.force.update'), [
            'password' => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ])->assertRedirect(route('affiliate.panel'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->actingAs($user)->get(route('affiliate.panel'))->assertRedirect(route('affiliate.profile.show'));

        $this->actingAs($user)->patch(route('affiliate.profile.update'), [
            'email' => $user->email,
            'phone' => '76543210',
            'address' => 'CALLE 1',
            'birth_date' => '1990-01-01',
            'marital_status' => 'SOLTERO',
            'photo' => UploadedFile::fake()->image('perfil.jpg', 600, 600),
        ])->assertRedirect(route('affiliate.profile.show'));

        $this->actingAs($user->fresh())->get(route('affiliate.panel'))
            ->assertOk()
            ->assertSee('revisión');
    }

    public function test_existing_approval_flow_generates_registration_once_and_keeps_sol(): void
    {
        [$sector, $plan] = $this->catalog();
        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan));
        $application = PublicAffiliationRequest::with('payment', 'affiliate')->firstOrFail();
        $admin = User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);

        app(\App\Services\PaymentLifecycleService::class)->confirm($application->payment, $admin);
        $application->refresh();
        $affiliate = $application->affiliate->fresh();

        $this->assertSame('approved', $application->status);
        $this->assertSame('activo', $affiliate->status);
        $this->assertNotNull($affiliate->registration_number);
        $this->assertSame($application->request_code, $application->fresh()->request_code);

        $registration = $affiliate->registration_number;
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\PaymentLifecycleService::class)->confirm($application->payment->fresh(), $admin);
        $this->assertSame($registration, $affiliate->fresh()->registration_number);
    }

    public function test_pending_affiliate_cannot_use_active_only_store(): void
    {
        [$sector, $plan] = $this->catalog();
        $this->post(route('public-affiliation.express.store'), $this->payload($sector, $plan));
        $user = User::where('email', '567864933@siafco.com')->firstOrFail();
        $user->forceFill(['must_change_password' => false])->save();
        $user->affiliate->update(['address' => 'CALLE', 'birth_date' => '1990-01-01', 'marital_status' => 'SOLTERO', 'photo_path' => 'affiliates/photos/test.jpg']);

        $this->actingAs($user)->get(route('store.catalog.index'))
            ->assertRedirect(route('affiliate.panel'));
    }

    private function catalog(string $code = 'ADU'): array
    {
        $sector = Sector::create(['name' => 'Adultos Mayores '.$code, 'code' => $code, 'is_active' => true]);
        $plan = AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'Afiliación Inicial',
            'affiliation_fee' => 200,
            'credential_fee' => 50,
            'is_active' => true,
        ]);

        return [$sector, $plan];
    }

    private function payload(Sector $sector, AffiliationPlan $plan, array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Sulema Condor',
            'ci' => '567864933',
            'issued_in' => 'BN',
            'phone' => '76543210',
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'transaction_number' => 'TRX-EXP-1',
            'bank_name' => 'Banco Unión',
            'payer_name' => 'Sulema Condor',
            'payment_date' => today()->format('Y-m-d'),
            'observations' => 'Pago QR',
        ], $overrides);
    }
}
