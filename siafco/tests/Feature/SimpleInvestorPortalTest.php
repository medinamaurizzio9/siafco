<?php

namespace Tests\Feature;

use App\Models\SimpleInvestmentDocumentVersion;
use App\Models\SimpleInvestmentInvestorAccess;
use App\Models\SimpleInvestmentPortalNotification;
use App\Models\User;
use App\Services\SimpleInvestmentContractService;
use App\Services\SimpleInvestmentInvestorService;
use App\Services\SimpleInvestorAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimpleInvestorPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_issues_hashed_temporary_access_and_investor_must_change_it(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = $this->investor($actor);
        $response = $this->actingAs($actor)->post(route('simple-investments.investors.portal-access.store', $investor));
        $temporary = $response->getSession()->get('portal_temporary_password');
        $access = SimpleInvestmentInvestorAccess::firstOrFail();
        $this->assertNotEmpty($temporary);
        $this->assertTrue(Hash::check($temporary, $access->password));
        $this->assertTrue($access->must_change_password);
        $this->post(route('simple-investor.login.store'), ['ci' => $investor->ci, 'password' => $temporary])->assertRedirect(route('simple-investor.password.edit'));
        $this->get(route('simple-investor.home'))->assertRedirect(route('simple-investor.password.edit'));
        $this->post(route('simple-investor.password.update'), ['password' => 'NuevaClave99', 'password_confirmation' => 'NuevaClave99'])->assertRedirect(route('simple-investor.home'));
        $this->assertFalse($access->fresh()->must_change_password);
    }

    public function test_login_is_rate_limited_account_locks_and_logout_invalidates_session(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = $this->investor($actor);
        [$access, $password] = app(SimpleInvestorAccessService::class)->issue($investor, $actor);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('simple-investor.login.store'), ['ci' => $investor->ci, 'password' => 'incorrecta']);
        }
        $this->assertTrue($access->fresh()->locked_until->isFuture());
        $this->post(route('simple-investor.login.store'), ['ci' => $investor->ci, 'password' => $password])->assertTooManyRequests();
        $this->withSession(['simple_investor_access_id' => $access->id, 'simple_investor_session_version' => $access->session_version])
            ->post(route('simple-investor.logout'))->assertRedirect(route('simple-investor.login'))->assertHeaderMissing('Clear-Site-Data');
        $this->get(route('simple-investor.home'))->assertRedirect(route('simple-investor.login'));
    }

    public function test_portal_only_exposes_owned_contract_units_and_verified_document(): void
    {
        Storage::fake('local');
        $actor = $this->user('supervisor_inversiones');
        [$mine, $myContract] = $this->signedContract($actor, '111111');
        [$other, $otherContract] = $this->signedContract($actor, '222222');
        $access = SimpleInvestmentInvestorAccess::create(['investor_id' => $mine->id, 'password' => Hash::make('ClaveSegura99'), 'must_change_password' => false, 'is_active' => true, 'session_version' => 1]);
        Storage::disk('local')->put('simple-investments/documents/test.pdf', '%PDF-test');
        $document = SimpleInvestmentDocumentVersion::create(['contract_id' => $myContract->id, 'version' => 1, 'original_name' => 'contrato.pdf', 'path' => 'simple-investments/documents/test.pdf', 'mime' => 'application/pdf', 'size_bytes' => 9, 'sha256' => str_repeat('a', 64), 'status' => 'verified', 'uploaded_by' => $actor->id, 'uploaded_at' => now(), 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->withSession(['simple_investor_access_id' => $access->id, 'simple_investor_session_version' => 1])
            ->get(route('simple-investor.contracts'))->assertOk()->assertSee($myContract->contract_number)->assertDontSee($otherContract->contract_number)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get(route('simple-investor.contracts.show', $otherContract))->assertNotFound();
        $this->get(route('simple-investor.documents.download', [$myContract, $document]))->assertOk();
    }

    public function test_notifications_are_private_readable_and_never_claim_external_delivery(): void
    {
        $actor = $this->user('supervisor_inversiones');
        $investor = $this->investor($actor);
        $other = $this->investor($actor, '333333');
        $access = SimpleInvestmentInvestorAccess::create(['investor_id' => $investor->id, 'password' => Hash::make('ClaveSegura99'), 'must_change_password' => false, 'is_active' => true, 'session_version' => 1]);
        $mine = SimpleInvestmentPortalNotification::create(['investor_id' => $investor->id, 'type' => 'advance_7', 'title' => 'Fecha próxima', 'body' => 'Consulta tu calendario privado.', 'reference_date' => today(), 'idempotency_key' => 'mine', 'generated_at' => now(), 'external_status' => 'not_configured']);
        $foreign = SimpleInvestmentPortalNotification::create(['investor_id' => $other->id, 'type' => 'due', 'title' => 'Otro', 'body' => 'Privado.', 'reference_date' => today(), 'idempotency_key' => 'other', 'generated_at' => now(), 'external_status' => 'not_configured']);
        $this->withSession(['simple_investor_access_id' => $access->id, 'simple_investor_session_version' => 1])->get(route('simple-investor.notifications'))->assertOk()->assertSee('Fecha próxima')->assertDontSee('Otro');
        $this->post(route('simple-investor.notifications.read', $mine))->assertRedirect();
        $this->assertNotNull($mine->fresh()->read_at);
        $this->post(route('simple-investor.notifications.read', $foreign))->assertNotFound();
        $this->assertSame('not_configured', $mine->fresh()->external_status);
        $this->assertNull($mine->fresh()->external_sent_at);
    }

    public function test_pwa_assets_are_installable_and_service_worker_never_caches_private_routes(): void
    {
        $manifest = file_get_contents(public_path('investor-pwa/manifest.webmanifest'));
        $worker = file_get_contents(public_path('investor-pwa/sw.js'));
        $this->assertStringContainsString('SIAFCO Mi Inversión', $manifest);
        $this->assertStringContainsString("startsWith('/mi-inversion/')", $worker);
        $this->assertStringContainsString("cache: 'no-store'", $worker);
        $this->assertStringNotContainsString('rendimiento', $worker);
    }

    public function test_worker_scope_manifest_and_protected_routes(): void
    {
        $this->get('/mi-inversion/sw.js')->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/mi-inversion/')
            ->assertHeader('Content-Type', 'application/javascript');
        $manifest = json_decode(file_get_contents(public_path('investor-pwa/manifest.webmanifest')), true);
        $this->assertSame('/mi-inversion/', $manifest['scope']);
        $this->assertSame('/mi-inversion/inicio', $manifest['start_url']);
        foreach (['home', 'profile', 'contracts', 'units', 'calendar', 'notifications'] as $route) {
            $this->get(route('simple-investor.'.$route))->assertRedirect(route('simple-investor.login'));
        }
        $this->actingAs($this->user('supervisor_inversiones'))
            ->get(route('simple-investor.contracts'))->assertRedirect(route('simple-investor.login'));
    }

    public function test_revoked_session_is_rejected_and_calendar_uses_future_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-01T04:01:00Z'));
        $actor = $this->user('supervisor_inversiones');
        [$investor, $contract] = $this->signedContract($actor, '444444');
        $access = SimpleInvestmentInvestorAccess::create(['investor_id' => $investor->id,
            'password' => Hash::make('ClaveSegura99'), 'must_change_password' => false,
            'is_active' => true, 'session_version' => 2]);
        $this->withSession(['simple_investor_access_id' => $access->id, 'simple_investor_session_version' => 1])
            ->get('/mi-inversion/')->assertOk()->assertSee('Cédula de identidad');
        $this->withSession(['simple_investor_access_id' => $access->id, 'simple_investor_session_version' => 2])
            ->get(route('simple-investor.home'))->assertOk()->assertSee('08/01/2027');
        $this->get(route('simple-investor.calendar'))->assertOk()->assertSee('Hoy')
            ->assertSee('Fechas anteriores para revisión')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->post(route('simple-investor.logout'))->assertRedirect()->assertHeaderMissing('Clear-Site-Data');
        $this->get(route('simple-investor.contracts.show', $contract))->assertRedirect(route('simple-investor.login'));
        $this->travelBack();
    }

    public function test_public_entry_is_independent_and_login_redirects_to_private_home(): void
    {
        $this->get('/mi-inversion')->assertOk()->assertSee('Cédula de identidad')
            ->assertSee('Soporte al inversionista')->assertDontSee('Registrarse');
        $this->get('/mi-inversion/acceso')->assertRedirect('/mi-inversion');
        $actor = $this->user('supervisor_inversiones');
        [$investor] = $this->signedContract($actor, '551122');
        $access = SimpleInvestmentInvestorAccess::create(['investor_id' => $investor->id,
            'password' => Hash::make('ClaveSegura99'), 'must_change_password' => false, 'is_active' => true, 'session_version' => 1]);
        $this->from('/mi-inversion')->post(route('simple-investor.login.store'), ['ci' => $investor->ci, 'password' => 'incorrecta'])
            ->assertRedirect('/mi-inversion')->assertSessionHasErrors('ci');
        $this->get(route('simple-investor.home'))->assertRedirect('/mi-inversion');
        $this->post(route('simple-investor.login.store'), ['ci' => $investor->ci, 'password' => 'ClaveSegura99'])
            ->assertRedirect(route('simple-investor.home'));
        $this->get('/mi-inversion')->assertRedirect(route('simple-investor.home'));
        $this->post(route('simple-investor.logout'))->assertRedirect('/mi-inversion');
        $this->get(route('simple-investor.home'))->assertRedirect('/mi-inversion');
        $this->assertTrue($access->fresh()->is_active);
    }

    public function test_responsive_views_share_one_navigation_and_all_sections(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01T12:00:00Z'));
        $this->preview('login', $this->get('/mi-inversion')->assertOk());
        $actor = $this->user('supervisor_inversiones');
        [$investor,$contract] = $this->signedContract($actor, '667788');
        $access = SimpleInvestmentInvestorAccess::create(['investor_id' => $investor->id, 'password' => Hash::make('ClaveSegura99'),
            'must_change_password' => false, 'is_active' => true, 'session_version' => 1]);
        $this->withSession(['simple_investor_access_id' => $access->id, 'simple_investor_session_version' => 1]);
        foreach (['home', 'contracts', 'units', 'calendar', 'notifications', 'profile'] as $section) {
            $response = $this->get(route('simple-investor.'.$section))->assertOk()->assertSee('Navegación principal');
            $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Navegación principal"'));
            foreach (['home', 'contracts', 'units', 'calendar', 'notifications', 'profile'] as $route) {
                $response->assertSee(route('simple-investor.'.$route), false);
            }
            $this->preview($section, $response);
        }
        $this->preview('contract', $this->get(route('simple-investor.contracts.show', $contract))->assertOk());
        $this->get(route('simple-investor.contracts'))->assertSee('<table', false)->assertSee('Ver detalle');
        $this->get(route('simple-investor.units'))->assertSee('ACC-000001');
        $this->travelBack();
    }

    private function preview(string $name, $response): void
    {
        $directory = getenv('INVESTOR_PREVIEW_DIR');
        if (! $directory) {
            return;
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        // Synthetic test fixtures only; static snapshots have no backend or credentials.
        $html = str_replace(['http://localhost', 'http://127.0.0.1:8000'], '', $response->getContent());
        $html = preg_replace('~<script\b[^>]*>.*?</script>~s', '', $html);
        file_put_contents($directory.'/'.$name.'.html', $html);
    }

    private function user(string $role): User
    {
        return User::create(['name' => $role, 'email' => $role.uniqid().'@test.local', 'role' => $role, 'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('secret')]);
    }

    private function investor(User $actor, string $ci = '998877')
    {
        return app(SimpleInvestmentInvestorService::class)->create(['full_name' => 'Ana Inversionista '.$ci, 'ci' => $ci, 'ci_complement' => null, 'expedition_department' => 'LP', 'phone' => '70000000', 'email' => $ci.'@test.local', 'address' => 'La Paz', 'registered_on' => '2026-10-30', 'is_active' => true, 'notes' => null], $actor);
    }

    private function signedContract(User $actor, string $ci): array
    {
        $investor = $this->investor($actor, $ci);
        $service = app(SimpleInvestmentContractService::class);
        $contract = $service->create(['investor_id' => $investor->id, 'quantity' => 2, 'registered_on' => '2026-10-30'], $actor);
        $service->markPending($contract);
        $service->sign($contract->fresh(), $actor, '2026-10-30');

        return [$investor, $contract->fresh()];
    }
}
