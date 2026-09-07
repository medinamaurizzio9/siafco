<?php

namespace Tests\Feature;

use App\Models\InvestmentAdvisor;
use App\Models\InvestmentAdvisorAccess;
use App\Models\InvestmentProspect;
use App\Models\User;
use App\Services\InvestmentProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestmentCrmKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_json_transitions_use_existing_workflow_history_and_audit(): void
    {
        [$admin, $advisor, $prospect] = $this->fixture();

        $this->actingAs($admin)->patchJson(route('investments.prospects.status', $prospect), ['status' => 'in_transition'])
            ->assertOk()->assertJson(['ok' => true, 'status' => 'in_transition', 'label' => 'EN TRANSICIÓN']);
        $this->patchJson(route('investments.prospects.status', $prospect), ['status' => 'closed'])
            ->assertOk()->assertJson(['ok' => true, 'status' => 'closed', 'label' => 'CERRADO']);

        $this->assertSame('closed', $prospect->refresh()->status);
        $this->assertSame(3, $prospect->statusHistory()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'investment_prospect_status_changed', 'auditable_id' => $prospect->id]);
    }

    public function test_invalid_direct_transition_is_rejected_as_json(): void
    {
        [$admin, , $prospect] = $this->fixture();
        $this->actingAs($admin)->patchJson(route('investments.prospects.status', $prospect), ['status' => 'closed'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('captured', $prospect->refresh()->status);
    }

    public function test_lost_requires_reason_from_captured_and_transition_states(): void
    {
        [$admin, , $prospect] = $this->fixture();
        $this->actingAs($admin)->patchJson(route('investments.prospects.status', $prospect), ['status' => 'lost'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->patchJson(route('investments.prospects.status', $prospect), ['status' => 'lost', 'reason' => 'Sin interés actual'])
            ->assertOk()->assertJson(['status' => 'lost']);

        $second = $this->prospect($prospect->currentAdvisor, '70000002');
        $this->patchJson(route('investments.prospects.status', $second), ['status' => 'in_transition'])->assertOk();
        $this->patchJson(route('investments.prospects.status', $second), ['status' => 'lost'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_admin_can_reopen_closed_and_lost_prospects_to_transition(): void
    {
        [$admin, , $closed] = $this->fixture();
        $closed->update(['status' => 'closed']);
        $lost = $this->prospect($closed->currentAdvisor, '70000003');
        $lost->update(['status' => 'lost']);

        $this->actingAs($admin)->patchJson(route('investments.prospects.status', $closed), ['status' => 'in_transition'])->assertOk();
        $this->patchJson(route('investments.prospects.status', $lost), ['status' => 'in_transition'])->assertOk();
    }

    public function test_advisor_can_move_own_prospect_but_cannot_reopen_closed_or_touch_foreign_one(): void
    {
        [, $advisor, $own] = $this->fixture();
        $access = InvestmentAdvisorAccess::create(['investment_advisor_id' => $advisor->id, 'login_code' => 'ABC234', 'pin_hash' => Hash::make('123456'), 'is_enabled' => true, 'must_change_pin' => false]);
        $otherAdvisor = $this->advisor('ASE-0002');
        $foreign = $this->prospect($otherAdvisor, '70000004');

        $this->withSession(['investment_crm_access_id' => $access->id])
            ->patchJson(route('investment-crm.advisor.prospects.status', $own), ['status' => 'in_transition'])
            ->assertOk()->assertJsonPath('status', 'in_transition');
        $own->update(['status' => 'closed']);
        $this->patchJson(route('investment-crm.advisor.prospects.status', $own), ['status' => 'in_transition'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson(route('investment-crm.advisor.prospects.status', $foreign), ['status' => 'in_transition'])->assertNotFound();
    }

    public function test_kanban_keeps_accessible_stage_controls_and_drag_metadata(): void
    {
        [$admin, , $prospect] = $this->fixture();
        $this->actingAs($admin)->get(route('investments.crm.kanban'))->assertOk()
            ->assertSee('data-crm-kanban', false)->assertSee('draggable="true"', false)
            ->assertSee('Cambiar etapa')->assertSee($prospect->prospect_number);
    }

    private function fixture(): array
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'kanban-admin@test.local', 'role' => 'superadministrador', 'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('secret')]);
        $advisor = $this->advisor('ASE-0001');
        return [$admin, $advisor, $this->prospect($advisor, '70000001')];
    }

    private function advisor(string $number): InvestmentAdvisor
    {
        return InvestmentAdvisor::create(['advisor_number' => $number, 'public_id' => (string) Str::uuid(), 'public_token' => Str::random(64), 'full_name' => $number, 'phone' => '71111111', 'is_active' => true]);
    }

    private function prospect(InvestmentAdvisor $advisor, string $phone): InvestmentProspect
    {
        return app(InvestmentProspectService::class)->createFromAdvisorQr($advisor, ['full_name' => 'Prospecto', 'phone' => $phone, 'requested_shares' => 20, 'preferred_contact_method' => 'whatsapp'], '127.0.0.1', 'Test')['prospect'];
    }
}
