<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentUnit;
use App\Models\User;
use App\Services\SimpleInvestmentContractService;
use App\Services\SimpleInvestmentInvestorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimpleInvestmentTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $actor = User::create(['name' => 'Transition test', 'email' => 'transition@example.test', 'role' => 'supervisor_inversiones',
            'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('SyntheticOnly99')]);
        $this->actingAs($actor);
        $investor = app(SimpleInvestmentInvestorService::class)->create(['full_name' => 'Synthetic investor', 'ci' => 'SYNTHETIC-TRANSITION',
            'registered_on' => '2026-10-30', 'is_active' => true], $actor);
        $service = app(SimpleInvestmentContractService::class);
        $contract = $service->create(['investor_id' => $investor->id, 'quantity' => 2, 'registered_on' => '2026-10-30'], $actor);

        return [$actor, $service, $contract];
    }

    private function snapshot(SimpleInvestmentContract $contract): array
    {
        return [$contract->fresh()->getAttributes(), SimpleInvestmentUnit::orderBy('id')->get()->toArray(),
            DB::table('simple_investment_sequences')->orderBy('key')->get()->toArray(), AuditLog::orderBy('id')->get()->toArray()];
    }

    private function rejected(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected controlled rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_draft_to_pending_reads_database_and_duplicate_request_has_no_effect(): void
    {
        [$actor,$service,$contract] = $this->fixture();
        $other = SimpleInvestmentContract::findOrFail($contract->id);
        $contract->status = SimpleInvestmentContract::VOID;
        $pending = $service->markPending($contract);
        $this->assertSame(SimpleInvestmentContract::PENDING_SIGNATURE, $pending->status);
        $before = $this->snapshot($pending);
        $this->rejected(fn () => $service->markPending($other));
        $this->assertEquals($before, $this->snapshot($pending));
    }

    public function test_signed_contract_rejects_stale_pending_without_changing_snapshots_or_units(): void
    {
        [$actor,$service,$contract] = $this->fixture();
        $stale = SimpleInvestmentContract::findOrFail($contract->id);
        $service->markPending($contract);
        $signed = $service->sign($contract, $actor, '2026-10-30');
        $before = $this->snapshot($signed);
        $this->rejected(fn () => $service->markPending($stale));
        $this->assertEquals($before, $this->snapshot($signed));
        $this->assertSame('2026-10-30', $signed->signed_on->format('Y-m-d'));
        $this->assertSame(['ACC-000001', 'ACC-000002'], $signed->units->pluck('code')->all());
        $again = $service->sign($stale, $actor, '2026-10-31');
        $this->assertEquals($before, $this->snapshot($again));
    }

    public function test_void_is_terminal_even_for_stale_instances_and_repeat_void_is_idempotent(): void
    {
        [$actor,$service,$contract] = $this->fixture();
        $stale = SimpleInvestmentContract::findOrFail($contract->id);
        $service->markPending($contract);
        $service->sign($contract, $actor, '2026-10-30');
        $void = $service->void($stale, $actor, 'Original audited cancellation');
        $before = $this->snapshot($void);
        $this->rejected(fn () => $service->markPending($stale));
        $this->rejected(fn () => $service->sign($stale, $actor, '2026-10-31'));
        $service->void($stale, $actor, 'Must not overwrite original reason');
        $this->assertEquals($before, $this->snapshot($void));
        $this->assertSame(SimpleInvestmentContract::VOID, $void->fresh()->status);
        $this->assertSame('2026-10-30', $void->fresh()->signed_on->format('Y-m-d'));
        $this->assertSame(2, $void->units()->count());
    }

    public function test_signature_from_draft_is_rejected_without_side_effects(): void
    {
        [$actor,$service,$contract] = $this->fixture();
        $before = $this->snapshot($contract);
        $this->rejected(fn () => $service->sign($contract, $actor, '2026-10-30'));
        $this->assertEquals($before, $this->snapshot($contract));
    }
}
