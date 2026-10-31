<?php

namespace Tests\Feature;

use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentDocumentVersion;
use App\Models\SimpleInvestmentInvestorAccess;
use App\Models\SimpleInvestmentPortalNotification;
use App\Models\User;
use App\Services\SimpleInvestmentContractService;
use App\Services\SimpleInvestmentInvestorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimpleInvestmentFinalAuditTest extends TestCase
{
    use RefreshDatabase;

    private function pair(): array
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $actor = User::create(['name' => 'Audit supervisor', 'email' => 'audit-supervisor@example.test',
            'role' => 'supervisor_inversiones', 'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('AuditOnly99')]);
        $result = [];
        foreach (['AUDIT-A', 'AUDIT-B'] as $ci) {
            $investor = app(SimpleInvestmentInvestorService::class)->create(['full_name' => $ci, 'ci' => $ci,
                'ci_complement' => null, 'expedition_department' => 'LP', 'registered_on' => '2026-10-30', 'is_active' => true], $actor);
            $contract = app(SimpleInvestmentContractService::class)->create(['investor_id' => $investor->id, 'quantity' => 1, 'registered_on' => '2026-10-30'], $actor);
            $access = SimpleInvestmentInvestorAccess::create(['investor_id' => $investor->id, 'password' => Hash::make('AuditOnly99'),
                'must_change_password' => false, 'is_active' => true, 'session_version' => 1]);
            $result[] = compact('investor', 'contract', 'access');
        }

        return [$actor, ...$result];
    }

    public function test_both_investors_cannot_cross_contracts_documents_notifications_or_admin(): void
    {
        Storage::fake('local');
        [$actor,$a,$b] = $this->pair();
        foreach ([$a, $b] as $owner) {
            $path = 'audit/'.$owner['investor']->id.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 audit synthetic');
            SimpleInvestmentDocumentVersion::create(['contract_id' => $owner['contract']->id, 'version' => 1, 'original_name' => 'audit.pdf',
                'path' => $path, 'mime' => 'application/pdf', 'size_bytes' => 24, 'sha256' => hash('sha256', '%PDF-1.4 audit synthetic'),
                'status' => 'verified', 'uploaded_by' => $actor->id, 'uploaded_at' => now(), 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            SimpleInvestmentPortalNotification::create(['investor_id' => $owner['investor']->id, 'contract_id' => $owner['contract']->id,
                'type' => 'due', 'title' => 'Private '.$owner['investor']->code, 'body' => 'Synthetic notice', 'generated_at' => now(),
                'idempotency_key' => 'audit:'.$owner['investor']->id, 'external_status' => 'not_configured']);
        }
        foreach ([[$a, $b], [$b, $a]] as [$mine,$other]) {
            $this->withSession(['simple_investor_access_id' => $mine['access']->id, 'simple_investor_session_version' => 1]);
            $this->get(route('simple-investor.profile'))->assertOk()->assertSee($mine['investor']->full_name)->assertDontSee($other['investor']->full_name);
            $this->get(route('simple-investor.contracts'))->assertOk()->assertSee($mine['contract']->contract_number)->assertDontSee($other['contract']->contract_number);
            $this->get(route('simple-investor.contracts.show', $other['contract']))->assertNotFound();
            $document = $other['contract']->documentVersions()->firstOrFail();
            $this->get(route('simple-investor.documents.download', [$other['contract'], $document]))->assertNotFound();
            $this->get(route('simple-investor.documents.download', [$mine['contract'], $document]))->assertNotFound();
            $notification = $other['investor']->portalNotifications()->firstOrFail();
            $this->get(route('simple-investor.notifications'))->assertOk()->assertDontSee($notification->title);
            $this->post(route('simple-investor.notifications.read', $notification))->assertNotFound();
            $this->get('/inversiones-simple')->assertRedirect();
            $this->assertGuest('web');
            $this->post(route('simple-investor.logout'))->assertRedirect('/mi-inversion');
            $this->get(route('simple-investor.home'))->assertRedirect('/mi-inversion');
        }
        $this->get('/mi-inversion/registro')->assertNotFound();
        $this->post('/mi-inversion/registro', ['ci' => 'AUTOREGISTER'])->assertNotFound();
        $this->assertDatabaseCount('simple_investment_investor_accesses', 2);
    }

    public function test_stale_draft_cannot_reopen_signed_contract(): void
    {
        [$actor,$a] = $this->pair();
        $service = app(SimpleInvestmentContractService::class);
        $stale = SimpleInvestmentContract::findOrFail($a['contract']->id);
        $service->markPending($a['contract']);
        $service->sign($a['contract']->fresh(), $actor, '2026-10-30');
        try {
            // Reproduce an overlapping request that loaded the original draft before signature.
            $service->markPending($stale);
        } catch (ValidationException) {
            // Rejecting an obsolete transition is the expected safe outcome.
        }
        $this->assertSame(SimpleInvestmentContract::SIGNED, $a['contract']->fresh()->status,
            'A stale draft must not overwrite a committed signature.');
        $this->assertSame(1, $a['contract']->units()->count());
    }

    public function test_csrf_rejects_mutations_without_token_even_when_not_logged_in(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        // Laravel normally skips CSRF in tests. Enable the real middleware on the isolated DB.
        $environment = $this->app->environment();
        $this->app->instance('env', 'csrf-audit');
        try {
            $this->post('/mi-inversion/acceso', ['ci' => 'AUDIT-NONE', 'password' => 'SyntheticOnly99'])->assertStatus(419);
            $this->post('/mi-inversion/salir')->assertStatus(419);
            $this->withSession(['_token' => 'synthetic-csrf-token'])
                ->post('/mi-inversion/acceso', ['_token' => 'synthetic-csrf-token', 'ci' => 'AUDIT-NONE', 'password' => 'SyntheticOnly99'])
                ->assertRedirect()->assertSessionHasErrors('ci');
        } finally {
            $this->app->instance('env', $environment);
        }
    }
}
