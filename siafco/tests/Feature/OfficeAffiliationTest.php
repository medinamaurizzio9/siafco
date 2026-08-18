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
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_office_receipt_pdf_route_and_template_use_original_payment_data(): void
    {
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
        ])->render();

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
        $cashierA = $this->internalUser('cajero');
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
            'marital_status' => 'SOLTERA',
            'regional' => 'LA PAZ',
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'institution' => '',
            'position' => '',
            'received_amount' => '120.00',
            'paid_at' => now()->format('Y-m-d\TH:i'),
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
