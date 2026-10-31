<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RolePermissionOverride;
use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentInvestor;
use App\Models\SimpleInvestmentSetting;
use App\Models\SimpleInvestmentUnit;
use App\Models\User;
use App\Services\SimpleInvestmentContractService;
use App\Services\SimpleInvestmentInvestorService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimpleInvestmentPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_configuration_and_module_are_independent(): void
    {
        $settings = SimpleInvestmentSetting::current();
        $this->assertSame('Acción', $settings->product_name);
        $this->assertSame('14000.00', $settings->unit_price);
        $this->assertSame(1, $settings->minimum_units);
        $this->assertSame(20, $settings->maximum_units);
        $this->assertDatabaseMissing('migrations', ['migration' => 'create_investment_tables']);
    }

    public function test_supervisor_can_use_module_but_not_crm_or_other_administration(): void
    {
        $user = $this->user('supervisor_inversiones');
        $this->actingAs($user)->get(route('simple-investments.dashboard'))->assertOk()->assertSee('INVERSIONES');
        $this->actingAs($user)->get(route('simple-investments.investors.index'))->assertOk();
        $this->actingAs($user)->get(route('investments.crm.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('affiliates.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_rrhh_has_no_automatic_access_but_explicit_override_can_grant_it(): void
    {
        $rrhh = $this->user('rrhh');
        $this->actingAs($rrhh)->get(route('simple-investments.dashboard'))->assertForbidden();
        RolePermissionOverride::create(['role' => 'rrhh', 'permissions' => ['rrhh.dashboard.view', 'simple_investments.dashboard.view'], 'updated_by' => $rrhh->id]);
        $this->actingAs($rrhh)->get(route('simple-investments.dashboard'))->assertOk();
    }

    public function test_authorized_rrhh_can_operate_but_never_supervise_or_configure(): void
    {
        $rrhh = $this->user('rrhh');
        RolePermissionOverride::create([
            'role' => 'rrhh',
            'permissions' => [
                'rrhh.dashboard.view', 'simple_investments.dashboard.view',
                'simple_investments.investors.view', 'simple_investments.investors.manage',
                'simple_investments.contracts.view', 'simple_investments.contracts.create',
                'simple_investments.contracts.transition', 'simple_investments.settings.manage',
            ],
            'updated_by' => $rrhh->id,
        ]);
        $this->actingAs($rrhh)->get(route('simple-investments.investors.create'))->assertOk();
        $this->actingAs($rrhh)->get(route('simple-investments.contracts.create'))->assertOk();
        $this->actingAs($rrhh)->get(route('simple-investments.settings.edit'))->assertForbidden();
        $this->assertFalse($rrhh->hasPermission('simple_investments.contracts.transition'));
        $this->assertFalse($rrhh->hasPermission('simple_investments.settings.manage'));

        $investor = app(SimpleInvestmentInvestorService::class)->create($this->investorPayload(), $rrhh);
        $contract = app(SimpleInvestmentContractService::class)->create(['investor_id' => $investor->id, 'quantity' => 1, 'registered_on' => '2026-10-30'], $rrhh);
        $this->actingAs($rrhh)->post(route('simple-investments.contracts.pending', $contract))->assertForbidden();
    }

    public function test_investor_crud_search_identity_and_private_photo(): void
    {
        Storage::fake('local');
        $actor = $this->user('supervisor_inversiones');
        $payload = $this->investorPayload(['photo' => UploadedFile::fake()->image('photo.jpg')]);
        $response = $this->actingAs($actor)->post(route('simple-investments.investors.store'), $payload);
        $investor = SimpleInvestmentInvestor::firstOrFail();
        $response->assertRedirect(route('simple-investments.investors.show', $investor));
        $this->assertSame('INV-000001', $investor->code);
        Storage::disk('local')->assertExists($investor->photo_path);
        $this->actingAs($actor)->get(route('simple-investments.investors.photo', $investor))->assertOk();
        $this->actingAs($this->user('gerente'))->get(route('simple-investments.investors.photo', $investor))->assertForbidden();
        $this->actingAs($actor)->get(route('simple-investments.investors.index', ['search' => 'Ana']))->assertSee('ANA INVERSIONISTA');
        $this->actingAs($actor)->post(route('simple-investments.investors.store'), $payload)->assertSessionHasErrors('identity_key');
    }

    public function test_contract_uses_server_price_and_keeps_historical_snapshot(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = app(SimpleInvestmentInvestorService::class)->create($this->investorPayload(), $actor);
        $contract = app(SimpleInvestmentContractService::class)->create(['investor_id' => $investor->id, 'quantity' => 3, 'registered_on' => '2026-10-30'], $actor);
        $this->assertSame('CON-000001', $contract->contract_number);
        $this->assertSame('14000.00', $contract->unit_price_snapshot);
        $this->assertSame('42000.00', $contract->total_amount);
        SimpleInvestmentSetting::current()->update(['unit_price' => '15000.00']);
        $this->assertSame('14000.00', $contract->fresh()->unit_price_snapshot);
    }

    public function test_private_photo_replacement_and_failed_write_do_not_leave_orphans(): void
    {
        Storage::fake('local');
        $actor = $this->user('supervisor_inversiones');
        $service = app(SimpleInvestmentInvestorService::class);
        $investor = $service->create($this->investorPayload(['photo' => UploadedFile::fake()->image('first.jpg')]), $actor);
        $oldPhoto = $investor->photo_path;
        $updated = $service->update($investor, $this->investorPayload(['photo' => UploadedFile::fake()->image('second.jpg')]), $actor);
        Storage::disk('local')->assertMissing($oldPhoto);
        Storage::disk('local')->assertExists($updated->photo_path);

        try {
            $service->create($this->investorPayload(['photo' => UploadedFile::fake()->image('orphan.jpg')]), $actor);
            $this->fail('La identidad duplicada debía ser rechazada por MySQL.');
        } catch (UniqueConstraintViolationException) {
            $this->assertCount(1, Storage::disk('local')->allFiles('simple-investments/investors'));
        }
    }

    public function test_signing_is_atomic_idempotent_and_issues_unique_units(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = app(SimpleInvestmentInvestorService::class)->create($this->investorPayload(), $actor);
        $service = app(SimpleInvestmentContractService::class);
        $contract = $service->create(['investor_id' => $investor->id, 'quantity' => 2, 'registered_on' => '2026-10-30'], $actor);
        $service->markPending($contract);
        $service->sign($contract, $actor, '2026-10-30');
        $service->sign($contract->fresh(), $actor, '2026-10-30');
        $this->assertSame(2, SimpleInvestmentUnit::count());
        $this->assertSame(['ACC-000001', 'ACC-000002'], SimpleInvestmentUnit::orderBy('id')->pluck('code')->all());
        $this->assertSame(SimpleInvestmentContract::SIGNED, $contract->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'simple_investment_contract_signed']);
    }

    public function test_signed_contract_is_not_deleted_and_void_preserves_units_and_trace(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = app(SimpleInvestmentInvestorService::class)->create($this->investorPayload(), $actor);
        $service = app(SimpleInvestmentContractService::class);
        $contract = $service->create(['investor_id' => $investor->id, 'quantity' => 1, 'registered_on' => '2026-10-30'], $actor);
        $service->markPending($contract);
        $service->sign($contract, $actor, '2026-10-30');
        $service->void($contract->fresh(), $actor, 'Corrección documental autorizada');
        $this->assertDatabaseHas('simple_investment_contracts', ['id' => $contract->id, 'status' => 'void']);
        $this->assertDatabaseCount('simple_investment_units', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'simple_investment_contract_voided']);
    }

    public function test_printable_summary_is_not_a_payment_receipt(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = app(SimpleInvestmentInvestorService::class)->create($this->investorPayload(), $actor);
        $contract = app(SimpleInvestmentContractService::class)->create(['investor_id' => $investor->id, 'quantity' => 1, 'registered_on' => '2026-10-30'], $actor);
        $this->actingAs($actor)->get(route('simple-investments.contracts.print', $contract))->assertOk()->assertSee('NO CONSTITUYE COMPROBANTE DE PAGO');
    }

    public function test_setting_changes_are_audited_and_superadministrator_has_full_access(): void
    {
        $super = $this->user('superadministrador');
        $this->actingAs($super)->put(route('simple-investments.settings.update'), ['product_name' => 'Acción', 'unit_price' => '14500.25', 'minimum_units' => 1, 'maximum_units' => 20, 'first_schedule_days' => 40, 'schedule_interval_days' => 30, 'is_active' => 1])->assertRedirect();
        $this->assertSame('14500.25', SimpleInvestmentSetting::current()->unit_price);
        $this->assertTrue(AuditLog::where('action', 'simple_investment_settings_updated')->exists());
    }

    public function test_all_administrative_screens_render_and_navigation_stays_in_module(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = app(SimpleInvestmentInvestorService::class)->create($this->investorPayload(), $actor);
        $contract = app(SimpleInvestmentContractService::class)->create(['investor_id' => $investor->id, 'quantity' => 1, 'registered_on' => '2026-10-30'], $actor);

        foreach ([
            route('simple-investments.dashboard'), route('simple-investments.investors.index'),
            route('simple-investments.investors.create'), route('simple-investments.investors.show', $investor),
            route('simple-investments.investors.edit', $investor), route('simple-investments.contracts.index'),
            route('simple-investments.contracts.create'), route('simple-investments.contracts.show', $contract),
            route('simple-investments.settings.edit'), route('simple-investments.contracts.print', $contract),
        ] as $url) {
            $this->actingAs($actor)->get($url)->assertOk();
        }

        $this->actingAs($actor)->get(route('simple-investments.dashboard'))
            ->assertSee('INVERSIONES')->assertSee('Resumen')->assertSee('Inversionistas')->assertSee('Contratos y ventas');
    }

    public function test_common_user_cannot_reach_direct_routes_or_mutations(): void
    {
        $common = $this->user('consulta');
        $supervisor = $this->user('supervisor_inversiones');
        $investor = app(SimpleInvestmentInvestorService::class)->create($this->investorPayload(), $supervisor);
        $contract = app(SimpleInvestmentContractService::class)->create(['investor_id' => $investor->id, 'quantity' => 1, 'registered_on' => '2026-10-30'], $supervisor);

        $this->actingAs($common)->get(route('simple-investments.dashboard'))->assertForbidden();
        $this->actingAs($common)->get(route('simple-investments.investors.show', $investor))->assertForbidden();
        $this->actingAs($common)->patch(route('simple-investments.investors.update', $investor), $this->investorPayload())->assertForbidden();
        $this->actingAs($common)->post(route('simple-investments.contracts.sign', $contract), ['signed_on' => '2026-10-30'])->assertForbidden();
        $this->actingAs($common)->put(route('simple-investments.settings.update'), [])->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => $role.uniqid().'@test.local', 'role' => $role, 'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('secret')]);
    }

    private function investorPayload(array $overrides = []): array
    {
        return array_merge(['full_name' => 'Ana Inversionista', 'ci' => '998877', 'ci_complement' => null, 'expedition_department' => 'LP', 'phone' => '70000000', 'email' => 'ana@test.local', 'address' => 'La Paz', 'registered_on' => '2026-10-30', 'is_active' => true, 'notes' => null], $overrides);
    }
}
