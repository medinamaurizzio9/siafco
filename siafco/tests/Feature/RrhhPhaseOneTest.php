<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RrhhWorker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RrhhPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_rrhh_registers_scheduled_monthly_and_daily_workers(): void
    {
        $actor = $this->user('rrhh');
        foreach ([['monthly', '2500.00'], ['daily', '120.00']] as [$mode, $amount]) {
            $response = $this->actingAs($actor)->post(route('rrhh.workers.store'), $this->scheduledPayload(['ci' => 'CI-'.$mode, 'compensation_mode' => $mode, 'agreed_amount' => $amount]));
            $response->assertRedirect();
        }
        $this->assertDatabaseHas('rrhh_workers', ['ci' => 'CI-monthly', 'compensation_mode' => 'monthly', 'agreed_amount' => '2500.00']);
        $this->assertDatabaseHas('rrhh_workers', ['ci' => 'CI-daily', 'compensation_mode' => 'daily', 'agreed_amount' => '120.00']);
        $this->assertSame(2, RrhhWorker::distinct('code')->count('code'));
    }

    public function test_virtual_worker_is_commission_only_and_rejects_fixed_data(): void
    {
        $actor = $this->user('rrhh');
        $this->actingAs($actor)->post(route('rrhh.workers.store'), ['type' => 'virtual', 'full_name' => 'Virtual Uno', 'ci' => 'V-1', 'phone' => '70000001', 'email' => 'virtual@example.test', 'joined_on' => '2026-10-01', 'compensation_mode' => 'commission_only', 'commissions_enabled' => 1, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('rrhh_workers', ['ci' => 'V-1', 'compensation_mode' => 'commission_only', 'agreed_amount' => null, 'commissions_enabled' => 1]);
        $this->actingAs($actor)->from(route('rrhh.workers.create'))->post(route('rrhh.workers.store'), ['type' => 'virtual', 'full_name' => 'Virtual Dos', 'ci' => 'V-2', 'phone' => '70000002', 'joined_on' => '2026-10-01', 'compensation_mode' => 'monthly', 'agreed_amount' => '1000', 'schedule_description' => '08:00 A 16:00', 'commissions_enabled' => 1, 'is_active' => 1])->assertSessionHasErrors('type');
        $this->assertDatabaseMissing('rrhh_workers', ['ci' => 'V-2']);
    }

    public function test_duplicate_ci_is_rejected_and_worker_is_toggled_without_deletion(): void
    {
        $actor = $this->user('rrhh');
        $this->actingAs($actor)->post(route('rrhh.workers.store'), $this->scheduledPayload())->assertRedirect();
        $worker = RrhhWorker::firstOrFail();
        $this->actingAs($actor)->post(route('rrhh.workers.store'), $this->scheduledPayload(['full_name' => 'OTRO']))->assertSessionHasErrors('ci');
        $this->actingAs($actor)->patch(route('rrhh.workers.toggle', $worker))->assertRedirect();
        $this->assertFalse($worker->fresh()->is_active);
        $this->assertDatabaseCount('rrhh_workers', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rrhh_worker_deactivated', 'auditable_id' => $worker->id]);
    }

    public function test_manager_reads_full_worker_but_cannot_edit_and_unprivileged_user_is_forbidden(): void
    {
        $worker = RrhhWorker::create([...$this->workerAttributes(), 'code' => 'TRA-000001', 'created_by' => null, 'updated_by' => null]);
        $manager = $this->user('gerente');
        $this->actingAs($manager)->get(route('rrhh.workers.show', $worker))->assertOk()->assertSee($worker->ci);
        $this->actingAs($manager)->get(route('rrhh.workers.edit', $worker))->assertForbidden();
        $this->actingAs($this->user('consulta'))->get(route('rrhh.dashboard'))->assertForbidden();
    }

    public function test_worker_account_only_sees_its_private_portal_and_not_administration(): void
    {
        $workerUser = $this->user('trabajador_rrhh');
        $worker = RrhhWorker::create([...$this->workerAttributes(), 'code' => 'TRA-000001', 'user_id' => $workerUser->id, 'created_by' => null, 'updated_by' => null]);
        $other = RrhhWorker::create([...$this->workerAttributes(), 'code' => 'TRA-000002', 'ci' => 'OTHER', 'full_name' => 'OTRO TRABAJADOR', 'created_by' => null, 'updated_by' => null]);
        $this->actingAs($workerUser)->get(route('rrhh.self.dashboard'))->assertOk()->assertSee($worker->code)->assertDontSee($other->code);
        $this->actingAs($workerUser)->get(route('rrhh.workers.index'))->assertForbidden();
        $this->actingAs($workerUser)->get('/dashboard')->assertRedirect(route('rrhh.self.dashboard'));
    }

    public function test_access_link_is_unique_and_new_account_does_not_gain_other_modules(): void
    {
        $actor = $this->user('rrhh');
        $worker = RrhhWorker::create([...$this->workerAttributes(), 'code' => 'TRA-000001', 'email' => 'new.worker@example.test', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $this->actingAs($actor)->post(route('rrhh.workers.access', $worker), ['mode' => 'create', 'access_email' => 'new.worker@example.test'])->assertRedirect()->assertSessionHas('rrhh_temporary_password');
        $account = User::where('email', 'new.worker@example.test')->firstOrFail();
        $this->assertSame('trabajador_rrhh', $account->role);
        $this->assertTrue($account->must_change_password);
        $this->assertFalse($account->hasPermission('affiliates.view'));
        $this->assertFalse($account->hasPermission('investment_prospects.view'));
        $this->assertSame($account->id, $worker->fresh()->user_id);
    }

    public function test_existing_account_can_only_be_linked_once_without_replacing_its_role(): void
    {
        $actor = $this->user('rrhh');
        $existing = $this->user('gerente');
        $first = RrhhWorker::create([...$this->workerAttributes(), 'code' => 'TRA-000001', 'email' => $existing->email, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $second = RrhhWorker::create([...$this->workerAttributes(), 'code' => 'TRA-000002', 'ci' => 'CI-TWO', 'email' => 'worker.two@example.test', 'created_by' => $actor->id, 'updated_by' => $actor->id]);

        $this->actingAs($actor)->post(route('rrhh.workers.access', $first), ['mode' => 'link', 'access_email' => $existing->email])->assertRedirect();
        $this->actingAs($actor)->post(route('rrhh.workers.access', $second), ['mode' => 'link', 'access_email' => $existing->email])->assertSessionHasErrors('access_email');

        $this->assertSame($existing->id, $first->fresh()->user_id);
        $this->assertNull($second->fresh()->user_id);
        $this->assertSame('gerente', $existing->fresh()->role);
        $this->actingAs($existing)->get(route('rrhh.self.dashboard'))->assertOk()->assertSee($first->code)->assertDontSee($second->code);
    }

    public function test_user_148_existing_role_is_not_replaced_by_phase_one(): void
    {
        $user = User::factory()->create(['id' => 148, 'role' => 'gerente', 'user_type' => 'internal', 'is_active' => true, 'must_change_password' => false]);
        $this->assertSame('gerente', $user->fresh()->role);
        $this->assertTrue($user->hasPermission('rrhh.workers.view'));
        $this->assertFalse($user->hasPermission('rrhh.workers.manage'));
        $this->assertSame(0, AuditLog::where('action', 'rrhh_initial_role_assigned')->count());
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'user_type' => 'internal', 'is_active' => true, 'must_change_password' => false]);
    }

    private function scheduledPayload(array $overrides = []): array
    {
        return array_merge(['type' => 'scheduled', 'full_name' => 'Trabajador Uno', 'ci' => 'CI-ONE', 'phone' => '70000000', 'email' => 'worker@example.test', 'position' => 'Analista', 'joined_on' => '2026-10-01', 'schedule_description' => '08:00 A 16:00', 'compensation_mode' => 'monthly', 'agreed_amount' => '2500.00', 'commissions_enabled' => 1, 'is_active' => 1], $overrides);
    }

    private function workerAttributes(): array
    {
        return ['type' => 'scheduled', 'full_name' => 'TRABAJADOR UNO', 'ci' => 'CI-ONE', 'phone' => '70000000', 'email' => 'worker@example.test', 'position' => 'ANALISTA', 'joined_on' => '2026-10-01', 'schedule_description' => '08:00 A 16:00', 'compensation_mode' => 'monthly', 'agreed_amount' => '2500.00', 'commissions_enabled' => true, 'is_active' => true];
    }
}
