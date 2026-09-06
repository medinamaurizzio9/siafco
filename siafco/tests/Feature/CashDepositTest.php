<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\CashDeposit;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\CashReconciliationService;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CashDepositTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_roles_use_panel_caja_as_their_single_first_dashboard_access(): void
    {
        foreach (['caja', 'cajero'] as $role) {
            $user = $this->user($role);

            $response = $this->actingAs($user)->get(route('cash-deposits.dashboard'));

            $response->assertOk()
                ->assertSeeInOrder(['PANEL CAJA', 'Afiliacion'])
                ->assertDontSee('Dashboard general')
                ->assertDontSee('>Dashboard<', false);
            $this->assertSame(1, substr_count($response->getContent(), 'PANEL CAJA'));

            auth()->logout();
        }
    }

    public function test_cash_roles_login_directly_to_panel_caja_and_dashboard_does_not_loop(): void
    {
        foreach (['caja', 'cajero'] as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'user_type' => 'internal',
                'is_active' => true,
                'password' => Hash::make('ClaveSegura2026'),
            ]);

            $this->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'ClaveSegura2026',
            ])->assertRedirect(route('cash-deposits.dashboard'));

            $this->get(route('admin.dashboard'))->assertRedirect(route('cash-deposits.dashboard'));
            $this->get(route('cash-deposits.dashboard'))->assertOk()->assertSee('Panel Caja');

            auth()->logout();
        }
    }

    public function test_management_roles_keep_the_general_dashboard_as_home(): void
    {
        foreach (['gerente', 'administrador', 'superadministrador'] as $role) {
            $user = $this->user($role);

            $this->assertSame('admin.dashboard', app(\App\Services\UserRedirectResolver::class)->homeRoute($user));
            $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee('Centro de operaciones');

            auth()->logout();
        }
    }

    public function test_cashier_dashboard_has_actions_and_only_confirmed_cash_is_reconcilable(): void
    {
        $cashier = $this->user('cajero');
        $affiliate = $this->affiliate();
        $this->payment($cashier, $affiliate, 'efectivo', PaymentStatus::UNDER_REVIEW, 250);
        $this->payment($cashier, $affiliate, 'qr', PaymentStatus::CONFIRMED, 300);
        $this->payment($cashier, $affiliate, 'transferencia', PaymentStatus::CONFIRMED, 400);

        $response = $this->followingRedirects()->actingAs($cashier)->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertSee('Panel Caja')->assertSee('Nueva afiliación en oficina')
            ->assertSee('Cobrar afiliación pendiente')->assertSee('Registrar depósito de caja')
            ->assertSee('Efectivo confirmado')->assertSee('Pendiente de depositar')
            ->assertSee('Depósitos en revisión')->assertSee('Depósitos confirmados')
            ->assertSee('Cobros en efectivo en revisión')->assertSee('BOB 250.00')
            ->assertSee('Disponible: BOB 0.00')
            ->assertSee('href="'.route('affiliates.office.create').'"', false)
            ->assertSee('href="'.route('public-affiliation.admin.secretary-payments').'"', false)
            ->assertSee('grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3', false)
            ->assertDontSee('md:col-span-2', false)
            ->assertSee('data-cash-table="payments"', false)
            ->assertSee('data-cash-table="deposits"', false)
            ->assertDontSee('Confirmar depósito');

        $summary = app(CashReconciliationService::class)->summary($cashier);
        $this->assertSame(0.0, $summary['confirmed_cash']);
        $this->assertSame(250.0, $summary['cash_under_review']);

        AffiliationPayment::where('payment_method', 'efectivo')->update(['status' => PaymentStatus::CONFIRMED]);
        $summary = app(CashReconciliationService::class)->summary($cashier);
        $this->assertSame(250.0, $summary['confirmed_cash']);
        $this->assertSame(250.0, $summary['available']);
    }

    public function test_cashier_creates_private_deposit_and_pending_amount_prevents_double_rendition(): void
    {
        Storage::fake('local');
        $cashier = $this->user('caja');
        $this->payment($cashier, $this->affiliate(), 'efectivo', PaymentStatus::CONFIRMED, 500);

        $payload = [
            'amount' => '350.00', 'transaction_number' => 'DEP-CAJA-001',
            'deposited_at' => now()->format('Y-m-d H:i:s'),
            'voucher' => UploadedFile::fake()->image('deposito.jpg'),
            'observations' => 'DEPÓSITO PARCIAL',
        ];
        $this->actingAs($cashier)->post(route('cash-deposits.store'), $payload)->assertSessionHasNoErrors();

        $deposit = CashDeposit::firstOrFail();
        $this->assertSame($cashier->id, $deposit->registered_by);
        $this->assertSame(CashDeposit::UNDER_REVIEW, $deposit->status);
        Storage::disk('local')->assertExists($deposit->voucher_path);
        $this->assertSame(150.0, app(CashReconciliationService::class)->summary($cashier)['available']);

        $this->actingAs($cashier)->post(route('cash-deposits.store'), [
            'amount' => '151.00', 'transaction_number' => 'DEP-EXCESO',
            'deposited_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('cash_deposits', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_deposit_created', 'auditable_id' => $deposit->id]);
    }

    public function test_rejection_releases_available_cash_and_confirmation_reduces_pending_total(): void
    {
        $cashier = $this->user('cajero');
        $manager = $this->user('gerente');
        $this->payment($cashier, $this->affiliate(), 'efectivo', PaymentStatus::CONFIRMED, 600);

        $this->actingAs($cashier)->post(route('cash-deposits.store'), $this->depositPayload(400, 'DEP-REJECT'))->assertSessionHasNoErrors();
        $first = CashDeposit::firstOrFail();
        $this->actingAs($cashier)->post(route('cash-deposits.admin.reject', $first), ['rejection_reason' => 'COMPROBANTE INCORRECTO'])->assertForbidden();
        $this->actingAs($manager)->post(route('cash-deposits.admin.reject', $first), [])->assertSessionHasErrors('rejection_reason');
        $this->actingAs($manager)->post(route('cash-deposits.admin.reject', $first), ['rejection_reason' => 'COMPROBANTE INCORRECTO'])->assertSessionHasNoErrors();
        $this->assertSame(600.0, app(CashReconciliationService::class)->summary($cashier)['available']);

        $this->actingAs($cashier)->post(route('cash-deposits.store'), $this->depositPayload(500, 'DEP-CONFIRM'))->assertSessionHasNoErrors();
        $second = CashDeposit::latest('id')->firstOrFail();
        $this->actingAs($cashier)->post(route('cash-deposits.admin.confirm', $second))->assertForbidden();
        $this->actingAs($manager)->post(route('cash-deposits.admin.confirm', $second))->assertSessionHasNoErrors();

        $summary = app(CashReconciliationService::class)->summary($cashier);
        $this->assertSame(500.0, $summary['confirmed_deposits']);
        $this->assertSame(100.0, $summary['pending_total']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_deposit_rejected', 'auditable_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cash_deposit_confirmed', 'auditable_id' => $second->id]);
    }

    public function test_administrative_roles_review_all_but_cashiers_only_see_their_own_data(): void
    {
        $firstCashier = $this->user('caja');
        $secondCashier = $this->user('cajero');
        $manager = $this->user('gerente');
        $admin = $this->user('administrador');
        foreach ([[$firstCashier, 'OWN-DEP'], [$secondCashier, 'OTHER-DEP']] as [$cashier, $number]) {
            $this->payment($cashier, $this->affiliate(), 'efectivo', PaymentStatus::CONFIRMED, 200);
            $this->actingAs($cashier)->post(route('cash-deposits.store'), $this->depositPayload(100, $number))->assertSessionHasNoErrors();
        }

        $this->actingAs($firstCashier)->get(route('cash-deposits.dashboard'))
            ->assertOk()->assertSee('OWN-DEP')->assertDontSee('OTHER-DEP');
        $this->actingAs($firstCashier)->get(route('cash-deposits.admin.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('cash-deposits.admin.index'))
            ->assertOk()->assertSee('OWN-DEP')->assertSee('OTHER-DEP')->assertSee('Ver');

        $deposit = CashDeposit::where('transaction_number', 'OWN-DEP')->firstOrFail();
        $this->actingAs($firstCashier)->get(route('cash-deposits.admin.show', $deposit))->assertForbidden();
        $this->actingAs($manager)->get(route('cash-deposits.admin.show', $deposit))->assertOk()->assertSee('OWN-DEP');
        $this->actingAs($admin)->post(route('cash-deposits.admin.confirm', $deposit))->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $deposit->fresh()->reviewed_by);
    }

    public function test_deposit_voucher_is_isolated_between_cashiers_and_available_to_reviewers(): void
    {
        Storage::fake('local');
        $owner = $this->user('caja');
        $other = $this->user('cajero');
        $manager = $this->user('gerente');
        $this->payment($owner, $this->affiliate(), 'efectivo', PaymentStatus::CONFIRMED, 100);
        $this->actingAs($owner)->post(route('cash-deposits.store'), $this->depositPayload(100, 'PRIVATE-DEP') + [
            'voucher' => UploadedFile::fake()->image('private.png'),
        ])->assertSessionHasNoErrors();
        $deposit = CashDeposit::firstOrFail();

        $this->actingAs($owner)->get(route('cash-deposits.voucher.own', $deposit))->assertOk();
        $this->actingAs($other)->get(route('cash-deposits.voucher.own', $deposit))->assertForbidden();
        $this->actingAs($manager)->get(route('cash-deposits.admin.voucher', $deposit))->assertOk();
    }

    public function test_management_dashboard_reports_pending_deposits_and_all_review_roles_can_confirm(): void
    {
        foreach (['gerente', 'administrador', 'superadministrador'] as $index => $role) {
            $cashier = $this->user('cajero');
            $this->payment($cashier, $this->affiliate(), 'efectivo', PaymentStatus::CONFIRMED, 100 + $index);
            $this->actingAs($cashier)->post(
                route('cash-deposits.store'),
                $this->depositPayload(100 + $index, 'DEP-REVIEW-'.$index)
            )->assertSessionHasNoErrors();

            $reviewer = $this->user($role);
            $this->actingAs($reviewer)->get(route('admin.dashboard'))
                ->assertOk()->assertSee('Rendiciones')->assertSee('por revisar');
            $deposit = CashDeposit::where('transaction_number', 'DEP-REVIEW-'.$index)->firstOrFail();
            $this->actingAs($reviewer)->post(route('cash-deposits.admin.confirm', $deposit))->assertSessionHasNoErrors();
            $this->assertSame(CashDeposit::CONFIRMED, $deposit->fresh()->status);
        }
    }

    private function depositPayload(float $amount, string $number): array
    {
        return ['amount' => $amount, 'transaction_number' => $number, 'deposited_at' => now()->format('Y-m-d H:i:s')];
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'user_type' => 'internal', 'is_active' => true]);
    }

    private function payment(User $cashier, Affiliate $affiliate, string $method, string $status, float $amount): AffiliationPayment
    {
        return AffiliationPayment::create([
            'affiliate_id' => $affiliate->id, 'affiliation_plan_id' => $affiliate->affiliation_plan_id,
            'amount' => $amount, 'paid_amount' => $amount, 'currency' => 'BOB',
            'payment_method' => $method, 'status' => $status, 'registered_by' => $cashier->id,
            'paid_at' => now(), 'confirmed_at' => PaymentStatus::isConfirmed($status) ? now() : null,
        ]);
    }

    private function affiliate(): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => strtoupper(fake()->unique()->lexify('???')), 'is_active' => true]);
        $plan = AffiliationPlan::create(['sector_id' => $sector->id, 'name' => fake()->unique()->word(), 'type' => 'independiente', 'affiliation_fee' => 100, 'credential_fee' => 20, 'currency' => 'BOB', 'is_active' => true]);
        $person = Person::create(['full_name' => fake()->name(), 'ci' => fake()->unique()->numerify('########')]);
        return Affiliate::create(['person_id' => $person->id, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'email' => fake()->unique()->safeEmail(), 'status' => 'pendiente_pago']);
    }
}
