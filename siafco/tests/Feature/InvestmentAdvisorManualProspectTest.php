<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\InvestmentAdvisor;
use App\Models\InvestmentAdvisorAccess;
use App\Models\InvestmentProspect;
use App\Models\InvestmentProspectInteraction;
use App\Models\Investor;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestmentAdvisorManualProspectTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_advisor_creates_own_manual_prospect_in_same_crm(): void
    {
        [$advisor, $access] = $this->advisorAccess();

        $this->withSession(['investment_crm_access_id' => $access->id])
            ->post(route('investment-crm.advisor.prospects.store'), [
                'full_name' => 'Prospecto Manual',
                'phone' => '+591 71234567',
                'requested_shares' => 25,
                'preferred_contact_method' => 'whatsapp',
            ])->assertRedirect();

        $prospect = InvestmentProspect::firstOrFail();
        $this->assertSame('PRO-000001', $prospect->prospect_number);
        $this->assertSame('captured', $prospect->status);
        $this->assertSame('advisor_manual', $prospect->source);
        $this->assertSame($advisor->id, $prospect->original_advisor_id);
        $this->assertSame($advisor->id, $prospect->current_advisor_id);
        $this->assertSame('59171234567', $prospect->phone_normalized);
        $this->assertSame(25, $prospect->requested_shares);
        $this->assertSame('whatsapp', $prospect->preferred_contact_method);
        $this->assertNotNull($prospect->captured_at);
        $this->assertNull($prospect->service_rating);
        $this->assertSame(0, InvestmentProspectInteraction::count());
        $this->assertSame(0, Person::count());
        $this->assertSame(0, Affiliate::count());
        $this->assertSame(0, Investor::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'investment_prospect_captured_manual']);
        $metadata = json_encode(AuditLog::firstWhere('action', 'investment_prospect_captured_manual')->metadata);
        $this->assertStringNotContainsString('71234567', $metadata);
        $this->withSession(['investment_crm_access_id' => $access->id])->get(route('investment-crm.advisor.prospects'))
            ->assertOk()->assertSee($prospect->prospect_number);
        $this->withSession(['investment_crm_access_id' => $access->id])->get(route('investment-crm.advisor.kanban'))
            ->assertOk()->assertSee($prospect->prospect_number)->assertSee('CAPTADO');
    }

    public function test_manual_capture_requires_valid_enabled_advisor_session(): void
    {
        $this->get(route('investment-crm.advisor.prospects.create'))->assertRedirect(route('investment-crm.advisor.login'));
        [$advisor, $access] = $this->advisorAccess();
        $access->update(['is_enabled' => false]);
        $this->withSession(['investment_crm_access_id' => $access->id])->get(route('investment-crm.advisor.prospects.create'))
            ->assertRedirect(route('investment-crm.advisor.login'));
        $access->update(['is_enabled' => true]);
        $advisor->update(['is_active' => false]);
        $this->withSession(['investment_crm_access_id' => $access->id])->get(route('investment-crm.advisor.prospects.create'))
            ->assertRedirect(route('investment-crm.advisor.login'));
    }

    public function test_manual_capture_rejects_advisor_and_rating_injection(): void
    {
        [$advisor, $access] = $this->advisorAccess();
        [$other] = $this->advisorAccess('ASE-0002');
        $this->withSession(['investment_crm_access_id' => $access->id])->post(route('investment-crm.advisor.prospects.store'), [
            'full_name' => 'Intento', 'phone' => '70000001', 'requested_shares' => 10,
            'preferred_contact_method' => 'call', 'advisor_id' => $other->id, 'service_rating' => 'very_good',
        ])->assertSessionHasErrors(['advisor_id', 'service_rating']);
        $this->assertSame(0, InvestmentProspect::count());
    }

    public function test_duplicate_policy_does_not_reassign_or_reveal_foreign_portfolio(): void
    {
        [$first, $firstAccess] = $this->advisorAccess();
        [$second, $secondAccess] = $this->advisorAccess('ASE-0002');
        $payload = ['full_name' => 'Original', 'phone' => '71234567', 'requested_shares' => 10, 'preferred_contact_method' => 'call'];
        $this->withSession(['investment_crm_access_id' => $firstAccess->id])->post(route('investment-crm.advisor.prospects.store'), $payload)->assertRedirect();
        $prospect = InvestmentProspect::firstOrFail();

        $this->withSession(['investment_crm_access_id' => $firstAccess->id])->post(route('investment-crm.advisor.prospects.store'), [...$payload, 'phone' => '+591 71234567'])
            ->assertRedirect(route('investment-crm.advisor.prospects.show', $prospect));
        $this->withSession(['investment_crm_access_id' => $secondAccess->id])->from(route('investment-crm.advisor.prospects.create'))
            ->post(route('investment-crm.advisor.prospects.store'), [...$payload, 'full_name' => 'Dato ajeno', 'phone' => '59171234567'])
            ->assertRedirect(route('investment-crm.advisor.prospects.create'))->assertSessionHasErrors('phone');

        $this->assertSame(1, InvestmentProspect::count());
        $this->assertSame($first->id, $prospect->refresh()->original_advisor_id);
        $this->assertSame($first->id, $prospect->current_advisor_id);
        $this->assertNotSame($second->id, $prospect->current_advisor_id);
    }

    public function test_authorized_administrator_can_assign_manual_prospect_without_rating(): void
    {
        [$advisor] = $this->advisorAccess();
        $admin = User::create([
            'name' => 'Administración', 'email' => 'manual-admin@test.local', 'role' => 'superadministrador',
            'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('secret'),
        ]);

        $this->actingAs($admin)->get(route('investments.prospects.create'))->assertOk()->assertSee('Asesor responsable');
        $this->post(route('investments.prospects.store'), [
            'full_name' => 'Prospecto Administrativo', 'phone' => '71112222', 'requested_shares' => 15,
            'preferred_contact_method' => 'call', 'advisor_id' => $advisor->id,
        ])->assertRedirect();

        $prospect = InvestmentProspect::firstOrFail();
        $this->assertSame($advisor->id, $prospect->current_advisor_id);
        $this->assertSame('advisor_manual', $prospect->source);
        $this->assertNull($prospect->service_rating);
    }

    private function advisorAccess(string $number = 'ASE-0001'): array
    {
        $advisor = InvestmentAdvisor::create([
            'advisor_number' => $number, 'public_id' => (string) Str::uuid(), 'public_token' => Str::random(64),
            'full_name' => "Asesor {$number}", 'phone' => '70000000', 'is_active' => true,
        ]);
        $access = InvestmentAdvisorAccess::create([
            'investment_advisor_id' => $advisor->id, 'login_code' => Str::upper(Str::random(6)),
            'pin_hash' => Hash::make('123456'), 'is_enabled' => true, 'must_change_pin' => false,
        ]);

        return [$advisor, $access];
    }
}
