<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\CredentialService;
use App\Support\PaymentStatus;
use App\Support\PublicAffiliationCatalogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class OfficeAffiliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_internal_roles_can_register_office_affiliations(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();

        foreach (['superadministrador', 'administrador', 'gerente', 'cajero', 'caja'] as $index => $role) {
            $actor = $this->internalUser($role);

            $response = $this->actingAs($actor)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'ci' => 'OFI-ROLE-'.$index,
                'email' => "oficina-role-{$index}@siafco.test",
                'reference_number' => "REC-ROLE-{$index}",
            ]));

            $payment = AffiliationPayment::latest('id')->firstOrFail();
            $response->assertRedirect(route('affiliates.office.show', $payment));
            $this->assertSame(PaymentStatus::CONFIRMED, $payment->status);
            $this->assertSame($actor->id, $payment->registered_by);
            $this->assertSame($actor->id, $payment->confirmed_by);
        }
    }

    public function test_external_affiliate_cannot_use_office_affiliation_flow(): void
    {
        [$sector, $plan] = $this->catalog();
        $affiliateUser = User::factory()->create(['role' => 'afiliado', 'user_type' => 'affiliate']);

        $this->actingAs($affiliateUser)
            ->get(route('affiliates.office.create'))
            ->assertForbidden();

        $this->actingAs($affiliateUser)
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan))
            ->assertForbidden();
    }

    public function test_office_affiliation_rejects_an_existing_user_email_with_a_human_message(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');
        User::factory()->create(['email' => 'duplicado@siafco.test']);
        $usersBefore = User::count();

        $response = $this->actingAs($cashier)
            ->from(route('affiliates.office.create'))
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'email' => 'duplicado@siafco.test',
            ]));

        $response->assertRedirect(route('affiliates.office.create'))
            ->assertSessionHasErrors([
                'email' => 'El correo electrónico ya está registrado en el sistema.',
            ])
            ->assertSessionHasInput('full_name', 'AFILIADA OFICINA');
        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseCount('affiliates', 0);
        $this->assertDatabaseCount('affiliation_payments', 0);
    }

    public function test_office_affiliation_rejects_an_existing_person_ci_without_creating_partial_records(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');
        Person::create(['full_name' => 'PERSONA EXISTENTE', 'ci' => 'OFI-001']);
        $peopleBefore = Person::count();
        $usersBefore = User::count();

        $response = $this->actingAs($cashier)
            ->from(route('affiliates.office.create'))
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan));

        $response->assertRedirect(route('affiliates.office.create'))
            ->assertSessionHasErrors([
                'ci' => 'El número de CI ya se encuentra registrado.',
            ]);
        $this->assertSame($peopleBefore, Person::count());
        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseCount('affiliates', 0);
        $this->assertDatabaseCount('affiliation_payments', 0);
    }

    public function test_repeated_office_affiliation_submission_does_not_create_duplicates(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');
        $payload = $this->payload($sector, $plan);

        $this->actingAs($cashier)->post(route('affiliates.office.store'), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post(route('affiliates.office.store'), $payload)
            ->assertSessionHasErrors(['ci', 'email']);

        $this->assertDatabaseCount('affiliates', 1);
        $this->assertDatabaseCount('affiliation_payments', 1);
        $this->assertSame(1, User::where('email', $payload['email'])->count());
    }

    public function test_office_form_and_global_layout_render_confirmation_and_notifications(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)
            ->withSession(['status' => 'Operación completada.', 'error' => 'Operación rechazada.'])
            ->get(route('affiliates.office.create'))
            ->assertOk()
            ->assertSee('data-confirm-office-affiliation', false)
            ->assertSee('Confirmar afiliación presencial')
            ->assertSee('data-confirm-modal', false)
            ->assertSee('Operación completada.')
            ->assertSee('Operación rechazada.');
    }

    public function test_office_payment_is_confirmed_without_voucher_and_activates_affiliate(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'received_amount' => '150.00',
                'reference_number' => 'REC-OFFICE-001',
            ]))
            ->assertSessionHasNoErrors();

        $affiliate = Affiliate::with('credential', 'user', 'person')->firstOrFail();
        $payment = AffiliationPayment::firstOrFail();

        $this->assertSame('activo', $affiliate->status);
        $this->assertSame('efectivo', $payment->payment_method);
        $this->assertSame('office_cash', $payment->source);
        $this->assertSame(PaymentStatus::CONFIRMED, $payment->status);
        $this->assertNull($payment->voucher_path);
        $this->assertSame($cashier->id, $payment->registered_by);
        $this->assertSame($cashier->id, $payment->confirmed_by);
        $this->assertSame(150.0, (float) $payment->paid_amount);
        $this->assertNotNull($payment->confirmed_at);
        $this->assertNotNull($payment->receipt_number);
        $this->assertMatchesRegularExpression('/^REC-\d{4}-\d{6}$/', $payment->receipt_number);
        $this->assertNotNull($affiliate->registration_number);
        $this->assertNotNull($affiliate->credential);
        $this->assertSame($affiliate->full_name, $affiliate->person->full_name);
        $this->assertSame('affiliate', $affiliate->user->user_type);

        $this->assertDatabaseHas('audit_logs', ['action' => 'office_affiliation_registered', 'auditable_id' => $affiliate->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office_cash_payment_received', 'auditable_id' => $payment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_confirmed', 'auditable_id' => $payment->id]);
    }

    public function test_office_payments_generate_unique_non_editable_receipt_numbers(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'ci' => 'OFI-UNIQ-1',
            'email' => 'office-unique-1@siafco.test',
            'reference_number' => 'MANUAL-001',
            'receipt_number' => 'REC-1900-999999',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($cashier)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'ci' => 'OFI-UNIQ-2',
            'email' => 'office-unique-2@siafco.test',
            'reference_number' => 'MANUAL-002',
            'receipt_number' => 'REC-1900-999999',
        ]))->assertSessionHasNoErrors();

        $receipts = AffiliationPayment::orderBy('id')->pluck('receipt_number');

        $this->assertCount(2, $receipts);
        $this->assertNotSame($receipts[0], $receipts[1]);
        $this->assertFalse($receipts->contains('REC-1900-999999'));
        $this->assertSame($receipts->unique()->count(), $receipts->count());
    }

    public function test_office_affiliation_rejects_insufficient_amount(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'received_amount' => '99.99',
            ]))
            ->assertSessionHasErrors('received_amount');

        $this->assertDatabaseCount('affiliates', 0);
        $this->assertDatabaseCount('affiliation_payments', 0);
    }

    public function test_failed_confirmation_rolls_back_office_affiliation_and_receipt(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');
        $credentials = Mockery::mock(CredentialService::class);
        $credentials->shouldReceive('generate')->once()->andThrow(new \RuntimeException('credential failed'));
        $this->app->instance(CredentialService::class, $credentials);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($cashier)
                ->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                    'ci' => 'OFI-FAIL',
                    'email' => 'office-fail@siafco.test',
                    'reference_number' => 'REC-FAIL',
                ]));
            $this->fail('Expected credential failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('credential failed', $exception->getMessage());
        }

        $this->assertDatabaseCount('affiliates', 0);
        $this->assertDatabaseCount('affiliation_payments', 0);
        $this->assertDatabaseMissing('affiliation_payments', ['receipt_number' => 'REC-FAIL']);
    }

    public function test_public_affiliation_and_traditional_payment_verification_remain_available(): void
    {
        [$sector, $plan] = $this->catalog();
        $secretary = $this->internalUser('secretaria');
        $affiliate = $this->existingAffiliate($sector, $plan);
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $plan->id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'qr',
            'reference_number' => 'QR-PENDING-001',
            'status' => PaymentStatus::UNDER_REVIEW,
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        $this->get(route('public-affiliation.create'))->assertOk();

        $this->actingAs($secretary)
            ->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Confirmar pago');
    }

    public function test_confirmed_office_cash_payments_are_not_pending_verification(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan))
            ->assertSessionHasNoErrors();

        $payment = AffiliationPayment::firstOrFail();

        $this->actingAs($cashier)
            ->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Confirmado')
            ->assertDontSee('Confirmar pago');

        $this->actingAs($cashier)
            ->get(route('payments.index', ['status' => PaymentStatus::PENDING]))
            ->assertOk()
            ->assertDontSee($payment->reference_number);
    }

    public function test_office_qr_requires_reference_and_remains_under_review_without_activation(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'payment_method' => 'qr',
            'reference_number' => '',
        ]))->assertSessionHasErrors('reference_number');

        $this->assertDatabaseCount('affiliation_payments', 0);

        $this->actingAs($cashier)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'payment_method' => 'qr',
            'reference_number' => 'TRX-OFFICE-001',
        ]))->assertSessionHasNoErrors()->assertSessionHas('status', 'Pago QR registrado correctamente. Queda pendiente de verificación por Gerencia.');

        $payment = AffiliationPayment::firstOrFail();
        $affiliate = Affiliate::with('credential')->firstOrFail();
        $this->assertSame('qr', $payment->payment_method);
        $this->assertSame('office_qr', $payment->source);
        $this->assertSame(PaymentStatus::UNDER_REVIEW, $payment->status);
        $this->assertSame('TRX-OFFICE-001', $payment->reference_number);
        $this->assertNull($payment->confirmed_by);
        $this->assertNull($payment->confirmed_at);
        $this->assertNull($payment->receipt_number);
        $this->assertSame('pendiente_pago', $affiliate->status);
        $this->assertNull($affiliate->credential);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office_qr_registered', 'auditable_id' => $payment->id]);
    }

    public function test_cashier_and_cash_roles_cannot_approve_or_reject_office_qr(): void
    {
        [$sector, $plan] = $this->catalog();
        $registrar = $this->internalUser('cajero');
        $this->actingAs($registrar)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'payment_method' => 'qr', 'reference_number' => 'TRX-ROLE-BLOCK',
        ]))->assertSessionHasNoErrors();
        $payment = AffiliationPayment::firstOrFail();

        foreach (['cajero', 'caja'] as $role) {
            $actor = $this->internalUser($role);
            $this->actingAs($actor)->post(route('payments.confirm', $payment))->assertForbidden();
            $this->actingAs($actor)->post(route('payments.reject', $payment), ['rejection_reason' => 'REFERENCIA INVALIDA'])->assertForbidden();
        }

        $this->assertSame(PaymentStatus::UNDER_REVIEW, $payment->fresh()->status);
    }

    public function test_office_qr_registrar_cannot_approve_own_payment_even_with_review_role(): void
    {
        [$sector, $plan] = $this->catalog();
        $manager = $this->internalUser('gerente');
        $this->actingAs($manager)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'payment_method' => 'qr', 'reference_number' => 'TRX-OWN-001',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($manager)->post(route('payments.confirm', AffiliationPayment::firstOrFail()))->assertForbidden();
    }

    public function test_management_roles_can_approve_office_qr_through_payment_lifecycle(): void
    {
        foreach (['gerente', 'administrador', 'superadministrador'] as $index => $role) {
            [$sector, $plan] = $this->catalog();
            $registrar = $this->internalUser('cajero');
            $this->actingAs($registrar)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'ci' => 'QR-APPROVE-'.$index,
                'email' => "qr-approve-{$index}@siafco.test",
                'payment_method' => 'qr',
                'reference_number' => 'TRX-APPROVE-'.$index,
            ]))->assertSessionHasNoErrors();
            $payment = AffiliationPayment::latest('id')->firstOrFail();
            $reviewer = $this->internalUser($role);

            $this->actingAs($reviewer)->post(route('payments.confirm', $payment))->assertSessionHasNoErrors();

            $payment->refresh();
            $this->assertSame(PaymentStatus::CONFIRMED, $payment->status);
            $this->assertSame($reviewer->id, $payment->confirmed_by);
            $this->assertNotNull($payment->receipt_number);
            $this->assertSame('activo', $payment->affiliate->status);
            $this->assertNotNull($payment->affiliate->credential);
            $this->assertDatabaseHas('audit_logs', ['action' => 'office_qr_approved', 'auditable_id' => $payment->id]);
        }
    }

    public function test_rejected_office_qr_does_not_activate_or_generate_receipt(): void
    {
        [$sector, $plan] = $this->catalog();
        $registrar = $this->internalUser('caja');
        $this->actingAs($registrar)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'payment_method' => 'qr', 'reference_number' => 'TRX-REJECT-001',
        ]))->assertSessionHasNoErrors();
        $payment = AffiliationPayment::firstOrFail();
        $reviewer = $this->internalUser('gerente');

        $this->actingAs($reviewer)->post(route('payments.reject', $payment), [
            'rejection_reason' => 'TRANSFERENCIA NO ENCONTRADA',
        ])->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertSame(PaymentStatus::REJECTED, $payment->status);
        $this->assertSame($reviewer->id, $payment->rejected_by);
        $this->assertNull($payment->receipt_number);
        $this->assertNotSame('activo', $payment->affiliate->status);
        $this->assertNull($payment->affiliate->credential);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office_qr_rejected', 'auditable_id' => $payment->id]);
    }

    public function test_pending_office_qr_is_separate_from_confirmed_collection_totals_and_scoped_to_registrar(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashierA = $this->internalUser('caja');
        $cashierB = $this->internalUser('cajero');
        $this->actingAs($cashierA)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'payment_method' => 'qr', 'reference_number' => 'TRX-PENDING-A',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($cashierA)->get(route('admin.collections.index'))
            ->assertOk()->assertSee('QR pendientes')->assertSee('TRX-PENDING-A')->assertSee('BOB 120.00');

        $this->actingAs($cashierB)->get(route('admin.collections.index'))
            ->assertOk()->assertDontSee('TRX-PENDING-A');

        $this->actingAs($this->internalUser('gerente'))->get(route('admin.collections.index'))
            ->assertOk()->assertSee('TRX-PENDING-A')->assertSee('Total confirmado')->assertSee('BOB 0.00');
    }

    public function test_office_summary_shows_registered_affiliation_details(): void
    {
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('superadministrador');

        $this->actingAs($admin)
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'full_name' => 'AFILIADA OFICINA RESUMEN',
                'ci' => 'OFI-SUM',
                'email' => 'oficina-resumen@siafco.test',
                'reference_number' => 'REC-SUMMARY',
            ]))
            ->assertSessionHasNoErrors();

        $payment = AffiliationPayment::firstOrFail();

        $this->actingAs($admin)
            ->get(route('affiliates.office.show', $payment))
            ->assertOk()
            ->assertSee('AFILIADA OFICINA RESUMEN')
            ->assertSee('OFI-SUM')
            ->assertSee('Efectivo / Oficina')
            ->assertSee('Pago confirmado')
            ->assertSee($payment->receipt_number)
            ->assertSee('Ver/Imprimir recibo')
            ->assertSee('Ver afiliado');
    }

    public function test_office_affiliation_form_uses_real_regional_and_marital_catalogs(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)
            ->get(route('affiliates.office.create'))
            ->assertOk()
            ->assertSee('name="regional"', false)
            ->assertSee('Seleccione regional')
            ->assertSee('value="LA PAZ"', false)
            ->assertSee('name="marital_status"', false)
            ->assertSee('Seleccione estado civil')
            ->assertSee('value="SOLTERO"', false)
            ->assertSee('Fotografía para credencial')
            ->assertSee('Encuadra el rostro dentro del área visible.');

        $this->actingAs($cashier)
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'regional' => 'OTRA REGIONAL',
                'marital_status' => 'UNION LIBRE',
            ]))
            ->assertSessionHasErrors(['regional', 'marital_status']);
    }

    public function test_admin_affiliate_form_uses_same_regional_marital_and_photo_sources(): void
    {
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('administrador');

        $this->actingAs($admin)
            ->get(route('affiliates.create'))
            ->assertOk()
            ->assertSee('name="regional"', false)
            ->assertSee('value="LA PAZ"', false)
            ->assertSee('name="marital_status"', false)
            ->assertSee('value="SOLTERO"', false)
            ->assertSee('data-photo-cropper', false)
            ->assertSee('Fotografía para credencial');

        $this->actingAs($admin)
            ->post(route('affiliates.store'), [
                'full_name' => 'AFILIADO ADMIN INVALIDO',
                'ci' => 'ADMIN-INVALID',
                'email' => 'admin-invalid@siafco.test',
                'sector_id' => $sector->id,
                'affiliation_plan_id' => $plan->id,
                'regional' => 'REGIONAL LIBRE',
                'marital_status' => 'CONVIVIENTE',
            ])
            ->assertSessionHasErrors(['regional', 'marital_status']);
    }

    public function test_office_photo_is_processed_for_credential_dimensions(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $cashier = $this->internalUser('cajero');

        $this->actingAs($cashier)
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
                'photo' => UploadedFile::fake()->image('vertical.png', 1400, 1900)->size(4800),
                'reference_number' => 'PHOTO-OFFICE-001',
            ]))
            ->assertSessionHasNoErrors();

        $affiliate = Affiliate::firstOrFail();
        Storage::disk('public')->assertExists($affiliate->photo_path);
        [$width, $height] = getimagesize(Storage::disk('public')->path($affiliate->photo_path));

        $this->assertSame([600, 760], [$width, $height]);
        $this->assertLessThan(512 * 1024, Storage::disk('public')->size($affiliate->photo_path));
        $this->assertSame($affiliate->photo_path, $affiliate->person->photo);
    }

    public function test_admin_affiliate_photo_is_processed_with_shared_service(): void
    {
        Storage::fake('public');
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('administrador');

        $this->actingAs($admin)
            ->post(route('affiliates.store'), [
                'full_name' => 'AFILIADO ADMIN FOTO',
                'ci' => 'ADMIN-PHOTO',
                'phone' => '70000002',
                'email' => 'admin-photo@siafco.test',
                'address' => 'CALLE ADMIN',
                'sector_id' => $sector->id,
                'affiliation_plan_id' => $plan->id,
                'regional' => PublicAffiliationCatalogs::REGIONALS[0],
                'marital_status' => PublicAffiliationCatalogs::MARITAL_STATUSES[0],
                'photo' => UploadedFile::fake()->image('admin.jpg', 1600, 2200)->size(4900),
            ])
            ->assertSessionHasNoErrors();

        $affiliate = Affiliate::firstOrFail();
        Storage::disk('public')->assertExists($affiliate->photo_path);
        [$width, $height] = getimagesize(Storage::disk('public')->path($affiliate->photo_path));

        $this->assertSame([600, 760], [$width, $height]);
        $this->assertSame($affiliate->photo_path, $affiliate->person->photo);
    }

    public function test_office_receipt_pdf_route_and_template_use_original_payment_data(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('institutional/logo/logo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));
        \App\Models\InstitutionalSetting::current()->update([
            'logo_path' => 'institutional/logo/logo.png',
            'institution_name' => 'COOPERATIVA TIERRA BENDITA',
        ]);
        \App\Models\InstitutionalSetting::clearCurrentCache();
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('superadministrador');

        $this->actingAs($admin)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'full_name' => 'AFILIADA RECIBO',
            'ci' => 'OFI-REC',
            'email' => 'office-receipt@siafco.test',
            'received_amount' => '120.00',
            'reference_number' => 'REC-HTML-001',
        ]))->assertSessionHasNoErrors();

        $payment = AffiliationPayment::with('affiliate.sector', 'affiliate.plan', 'cashier', 'registrar')->firstOrFail();
        $receiptNumber = $payment->receipt_number;
        $original = $payment->only(['receipt_number', 'paid_amount', 'amount', 'status', 'confirmed_by']);

        $html = view('payments.receipt', [
            'payment' => $payment,
            'institution' => \App\Models\InstitutionalSetting::current(),
            'statusLabel' => PaymentStatus::label($payment->status),
            'logoSrc' => 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get('institutional/logo/logo.png')),
        ])->render();

        $this->assertStringContainsString('Logo institucional', $html);
        $this->assertStringContainsString('SIAFCO', $html);
        $this->assertStringContainsString('<h1 class="receipt-title">RECIBO</h1>', $html);
        $this->assertStringContainsString('COOPERATIVA TIERRA BENDITA', mb_strtoupper($html));
        $this->assertStringContainsString('Total pagado', $html);
        $this->assertStringContainsString($receiptNumber, $html);
        $this->assertStringContainsString('AFILIADA RECIBO', $html);
        $this->assertStringContainsString('OFI-REC', $html);
        $this->assertStringContainsString('BOB 120.00', $html);
        $this->assertStringContainsString($admin->name, $html);
        $this->assertStringContainsString('Efectivo / Pago en oficina', $html);
        $this->assertStringContainsString('CONFIRMADO', $html);

        $this->actingAs($admin)
            ->get(route('admin.payments.receipt', $payment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.receipt', [
            'payment' => $payment,
            'institution' => \App\Models\InstitutionalSetting::current(),
            'statusLabel' => PaymentStatus::label($payment->status),
            'logoSrc' => 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get('institutional/logo/logo.png')),
        ])->setPaper('a4');
        $pdf->getDomPDF()->render();
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());

        $this->actingAs($admin)->get(route('admin.payments.receipt', $payment))->assertOk();

        $payment->refresh();
        $this->assertSame($receiptNumber, $payment->receipt_number);
        $this->assertDatabaseCount('affiliation_payments', 1);
        $this->assertSame($original['paid_amount'], $payment->paid_amount);
        $this->assertSame($original['amount'], $payment->amount);
        $this->assertSame($original['status'], $payment->status);
        $this->assertSame($original['confirmed_by'], $payment->confirmed_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'receipt_printed',
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_unauthorized_user_cannot_print_office_receipt(): void
    {
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('superadministrador');
        $consulta = $this->internalUser('consulta');

        $this->actingAs($admin)->post(route('affiliates.office.store'), $this->payload($sector, $plan))
            ->assertSessionHasNoErrors();

        $payment = AffiliationPayment::firstOrFail();

        $this->actingAs($consulta)
            ->get(route('admin.payments.receipt', $payment))
            ->assertForbidden();
    }

    public function test_pending_payment_cannot_be_rendered_as_confirmed_receipt(): void
    {
        [$sector, $plan] = $this->catalog();
        $admin = $this->internalUser('superadministrador');
        $affiliate = $this->existingAffiliate($sector, $plan);
        $payment = AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'affiliation_plan_id' => $plan->id,
            'amount' => 120,
            'paid_amount' => 120,
            'expected_amount' => 120,
            'currency' => 'BOB',
            'payment_method' => 'efectivo',
            'status' => PaymentStatus::PENDING,
            'source' => 'manual_admin',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.receipt', $payment))
            ->assertNotFound();
    }

    public function test_collection_report_lists_filters_totals_and_receipt_access(): void
    {
        [$sector, $plan] = $this->catalog();
        $manager = $this->internalUser('gerente');
        $cashierA = $this->internalUser('caja');
        $cashierB = $this->internalUser('cajero');

        $this->actingAs($cashierA)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'full_name' => 'COBRO CAJERO UNO',
            'ci' => 'COBRO-1',
            'email' => 'cobro-uno@siafco.test',
            'received_amount' => '120.00',
            'reference_number' => 'COBRO-REF-1',
            'paid_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();
        $first = AffiliationPayment::latest('id')->firstOrFail();

        $this->actingAs($cashierB)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'full_name' => 'COBRO CAJERO DOS',
            'ci' => 'COBRO-2',
            'email' => 'cobro-dos@siafco.test',
            'received_amount' => '240.00',
            'reference_number' => 'COBRO-REF-2',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasNoErrors();
        $second = AffiliationPayment::latest('id')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('admin.collections.index', ['cashier_id' => $cashierA->id]))
            ->assertOk()
            ->assertSee($first->receipt_number)
            ->assertSee('COBRO CAJERO UNO')
            ->assertSee('BOB 120.00')
            ->assertSee('Ver recibo')
            ->assertSee('Imprimir')
            ->assertDontSee('COBRO CAJERO DOS');

        $this->actingAs($manager)
            ->get(route('admin.collections.index', ['receipt_number' => $second->receipt_number]))
            ->assertOk()
            ->assertSee($second->receipt_number)
            ->assertSee('BOB 240.00');

        $this->actingAs($manager)
            ->get(route('admin.collections.index', ['ci' => 'COBRO-1']))
            ->assertOk()
            ->assertSee('COBRO-1')
            ->assertDontSee('COBRO-2');

        $this->actingAs($manager)
            ->get(route('admin.collections.index', [
                'date_from' => now()->subDays(2)->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]))
            ->assertOk()
            ->assertSee('Cantidad de cobros')
            ->assertSee('BOB 360.00');

        $this->actingAs($cashierA)
            ->get(route('admin.collections.index', ['cashier_id' => $cashierB->id]))
            ->assertOk()
            ->assertSee('COBRO CAJERO UNO')
            ->assertDontSee('COBRO CAJERO DOS');
    }

    public function test_super_admin_admin_and_manager_can_filter_collections_by_any_cashier(): void
    {
        [$sector, $plan] = $this->catalog();
        $cashierA = $this->internalUser('cajero');
        $cashierB = $this->internalUser('cajero');

        $this->actingAs($cashierA)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'full_name' => 'COBRO FILTRABLE UNO',
            'ci' => 'FILTER-1',
            'email' => 'filter-one@siafco.test',
            'reference_number' => 'FILTER-REF-1',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($cashierB)->post(route('affiliates.office.store'), $this->payload($sector, $plan, [
            'full_name' => 'COBRO FILTRABLE DOS',
            'ci' => 'FILTER-2',
            'email' => 'filter-two@siafco.test',
            'reference_number' => 'FILTER-REF-2',
        ]))->assertSessionHasNoErrors();

        foreach (['superadministrador', 'administrador', 'gerente'] as $role) {
            $this->actingAs($this->internalUser($role))
                ->get(route('admin.collections.index', ['cashier_id' => $cashierB->id]))
                ->assertOk()
                ->assertSee('COBRO FILTRABLE DOS')
                ->assertDontSee('COBRO FILTRABLE UNO');
        }
    }

    public function test_office_affiliation_rejects_plan_from_another_sector_without_partial_records(): void
    {
        [$sector, $plan] = $this->catalog();
        $otherSector = Sector::create(['name' => 'OTRO SECTOR', 'code' => 'OTR', 'is_active' => true]);
        $plan->update(['sector_id' => $otherSector->id]);

        $this->actingAs($this->internalUser('cajero'))
            ->post(route('affiliates.office.store'), $this->payload($sector, $plan))
            ->assertSessionHasErrors(['affiliation_plan_id']);

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('affiliates', 0);
        $this->assertDatabaseCount('affiliation_payments', 0);
    }

    public function test_office_form_exposes_only_available_sector_plan_options(): void
    {
        [$sector, $plan] = $this->catalog();
        $inactive = AffiliationPlan::create([
            'sector_id' => $sector->id, 'name' => 'PLAN INACTIVO', 'type' => 'independiente',
            'affiliation_fee' => 50, 'credential_fee' => 10, 'currency' => 'BOB', 'is_active' => false,
        ]);

        $this->actingAs($this->internalUser('cajero'))->get(route('affiliates.office.create'))
            ->assertOk()
            ->assertSee('data-sector-plan-select', false)
            ->assertSee('data-sector="'.$sector->id.'"', false)
            ->assertSee($plan->name)
            ->assertDontSee($inactive->name);
    }

    public function test_plan_listing_shows_and_filters_sector_and_keeps_historical_general_plan(): void
    {
        [$sector, $plan] = $this->catalog();
        $general = AffiliationPlan::create([
            'name' => 'PLAN HISTORICO GENERAL', 'type' => 'independiente',
            'affiliation_fee' => 50, 'credential_fee' => 0, 'currency' => 'BOB', 'is_active' => true,
        ]);
        $admin = $this->internalUser('superadministrador');

        $this->actingAs($admin)->get(route('plans.index'))
            ->assertOk()->assertSee($sector->name)->assertSee('General / Sin sector');

        $this->actingAs($admin)->get(route('plans.index', ['sector_id' => $sector->id]))
            ->assertOk()->assertSee($plan->name)->assertDontSee($general->name);

        $this->actingAs($admin)->get(route('plans.edit', $plan))
            ->assertOk()->assertSee('value="'.$sector->id.'" selected', false);
    }

    private function catalog(): array
    {
        $sector = Sector::create([
            'name' => 'SALUD OFICINA',
            'code' => 'OFI'.fake()->unique()->numberBetween(100, 999),
            'regional' => 'LA PAZ',
            'institution' => 'SIAFCO',
            'is_active' => true,
        ]);
        $plan = AffiliationPlan::create([
            'sector_id' => $sector->id,
            'name' => 'PLAN OFICINA',
            'type' => 'independiente',
            'affiliation_fee' => 100,
            'credential_fee' => 20,
            'currency' => 'BOB',
            'is_active' => true,
        ]);

        return [$sector, $plan];
    }

    private function payload(Sector $sector, AffiliationPlan $plan, array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'AFILIADA OFICINA',
            'ci' => 'OFI-001',
            'phone' => '70000001',
            'email' => 'afiliada-oficina@siafco.test',
            'address' => 'AVENIDA OFICINA 123',
            'birth_date' => '1990-01-15',
            'marital_status' => 'SOLTERO',
            'regional' => 'LA PAZ',
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'institution' => '',
            'position' => '',
            'received_amount' => '120.00',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'payment_method' => 'efectivo',
            'reference_number' => 'REC-OFFICE',
            'observations' => 'COBRO PRESENCIAL',
        ], $overrides);
    }

    private function internalUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
        ]);
    }

    private function existingAffiliate(Sector $sector, AffiliationPlan $plan): Affiliate
    {
        $person = Person::create(['full_name' => 'AFILIADO QR', 'ci' => 'QR-001']);
        $user = User::factory()->create([
            'person_id' => $person->id,
            'name' => 'AFILIADO QR',
            'email' => 'afiliado-qr@siafco.test',
            'role' => 'afiliado',
            'user_type' => 'affiliate',
            'password' => Hash::make('Secret1234'),
        ]);

        return Affiliate::create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'AFILIADO QR',
            'ci' => 'QR-001',
            'email' => $user->email,
            'registration_number' => 'QR-000001',
            'verification_token' => 'qr-test-token',
            'status' => 'pendiente_pago',
        ]);
    }
}
