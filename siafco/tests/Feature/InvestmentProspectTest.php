<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\InvestmentAdvisor;
use App\Models\InvestmentCrmSequence;
use App\Models\InvestmentLot;
use App\Models\InvestmentProspect;
use App\Models\Investor;
use App\Models\Person;
use App\Models\ShareReservation;
use App\Models\User;
use App\Services\InvestmentProspectService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestmentProspectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::create([
            'name' => 'Administración',
            'email' => 'prospect-admin@test.local',
            'role' => 'superadministrador',
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('secret'),
        ]);
    }

    public function test_active_advisor_qr_shows_public_form_and_invalid_or_inactive_advisor_is_hidden(): void
    {
        $advisor = $this->advisor('ASE-0001', 'Asesora Activa');

        $this->get(route('investments.advisors.public', $advisor->public_token))
            ->assertOk()
            ->assertSee('Quiero información para invertir')
            ->assertSee('Nombre completo')
            ->assertSee('Número de celular')
            ->assertSee('Cantidad de acciones')
            ->assertSee('WhatsApp')
            ->assertSee('Llamada');

        $this->get(route('investments.advisors.public', 'invalid-token'))->assertNotFound();
        $advisor->update(['is_active' => false]);
        $this->get(route('investments.advisors.public', $advisor->public_token))->assertNotFound();
    }

    public function test_public_form_validates_only_allowed_capture_fields(): void
    {
        $advisor = $this->advisor();
        $route = route('investments.advisors.public.store', $advisor->public_token);

        $this->post($route, [...$this->payload(), 'full_name' => ''])->assertSessionHasErrors('full_name');
        $this->post($route, [...$this->payload(), 'phone' => ''])->assertSessionHasErrors('phone');
        $this->post($route, [...$this->payload(), 'requested_shares' => 0])->assertSessionHasErrors('requested_shares');
        $this->post($route, [...$this->payload(), 'preferred_contact_method' => 'email'])->assertSessionHasErrors('preferred_contact_method');
        $this->assertSame(0, InvestmentProspect::count());
    }

    public function test_public_form_accepts_optional_service_ratings_and_records_safe_audit(): void
    {
        foreach (['poor', 'regular', 'good', 'very_good'] as $index => $rating) {
            $advisor = $this->advisor('ASE-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), "Asesor {$index}");
            $this->post(route('investments.advisors.public.store', $advisor->public_token), [
                ...$this->payload("Prospecto {$rating}", '7000'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)),
                'service_rating' => $rating,
            ])->assertRedirect();
            $prospect = InvestmentProspect::where('service_rating', $rating)->firstOrFail();
            $this->assertNotNull($prospect->service_rating_at);
            $this->assertSame('public_form', $prospect->service_rating_source);
        }

        $advisor = $this->advisor('ASE-0099', 'Sin calificación');
        $this->post(route('investments.advisors.public.store', $advisor->public_token), $this->payload('Sin rating', '71110000'))->assertRedirect();
        $this->assertNull(InvestmentProspect::where('full_name', 'Sin rating')->firstOrFail()->service_rating);
        $this->assertSame(4, AuditLog::where('action', 'investment_prospect_service_rated')->count());
        $metadata = json_encode(AuditLog::where('action', 'investment_prospect_service_rated')->firstOrFail()->metadata);
        $this->assertStringNotContainsString('70000000', $metadata);
    }

    public function test_invalid_rating_fails_and_administrative_filter_uses_real_values(): void
    {
        $advisor = $this->advisor();
        $this->post(route('investments.advisors.public.store', $advisor->public_token), [...$this->payload(), 'service_rating' => 'excellent'])
            ->assertSessionHasErrors('service_rating');
        $this->capture($advisor, [...$this->payload('Muy buena', '70000001'), 'service_rating' => 'very_good']);
        $this->capture($advisor, $this->payload('Prospecto sin rating', '70000002'));

        $this->actingAs($this->admin)->get(route('investments.prospects.index', ['service_rating' => 'very_good']))
            ->assertOk()->assertSee('Muy buena')->assertDontSee('Prospecto sin rating');
        $this->get(route('investments.prospects.index', ['service_rating' => 'none']))
            ->assertOk()->assertSee('Prospecto sin rating')->assertDontSee('>Muy buena</td>', false);
    }

    public function test_capture_generates_independent_pro_numbers_and_all_backend_owned_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-06 22:48:31', config('app.timezone')));
        $advisor = $this->advisor('ASE-0012', 'Carlos López');

        $firstResponse = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->withHeader('User-Agent', 'Prospect Browser/1.0')
            ->post(route('investments.advisors.public.store', $advisor->public_token), [
                ...$this->payload('Juan Pérez', '712 345 67'),
                'captured_at' => '2000-01-01 00:00:00',
                'status' => 'closed',
                'source' => 'manual',
                'original_advisor_id' => 999,
            ]);
        $firstResponse->assertRedirect(route('investments.advisors.public', $advisor->public_token));

        $this->post(route('investments.advisors.public.store', $advisor->public_token), $this->payload('María Paz', '70002000'))
            ->assertRedirect();

        $first = InvestmentProspect::query()->oldest('id')->firstOrFail();
        $second = InvestmentProspect::query()->latest('id')->firstOrFail();

        $this->assertSame('PRO-000001', $first->prospect_number);
        $this->assertSame('PRO-000002', $second->prospect_number);
        $this->assertSame(1, InvestmentCrmSequence::firstWhere('key', 'investment_advisor')->next_number);
        $this->assertSame(3, InvestmentCrmSequence::firstWhere('key', 'investment_prospect')->next_number);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $first->public_id);
        $this->assertSame('Juan Pérez', $first->full_name);
        $this->assertSame('71234567', $first->phone);
        $this->assertSame('59171234567', $first->phone_normalized);
        $this->assertSame(10, $first->requested_shares);
        $this->assertSame('whatsapp', $first->preferred_contact_method);
        $this->assertSame($advisor->id, $first->original_advisor_id);
        $this->assertSame($advisor->id, $first->current_advisor_id);
        $this->assertSame('captured', $first->status);
        $this->assertSame('advisor_qr', $first->source);
        $this->assertSame('2026-09-06 22:48:31', $first->captured_at->format('Y-m-d H:i:s'));
        $this->assertTrue($first->captured_at->equalTo($first->contact_consent_at));
        $this->assertSame('192.0.2.10', $first->capture_ip);
        $this->assertSame('Prospect Browser/1.0', $first->capture_user_agent);
        $this->assertTrue($first->isCaptured());
        $this->assertFalse($first->isConverted());

        $this->assertSame(0, Person::count());
        $this->assertSame(0, Affiliate::count());
        $this->assertSame(0, Investor::count());
        $this->assertSame(0, ShareReservation::count());
        $this->assertSame(0, InvestmentLot::count());
    }

    public function test_bolivian_phone_variations_are_equivalent_and_duplicate_is_neutral_without_reassignment_or_sequence_consumption(): void
    {
        $firstAdvisor = $this->advisor('ASE-0001', 'Asesora Original');
        $secondAdvisor = $this->advisor('ASE-0002', 'Asesor Nuevo');
        $service = app(InvestmentProspectService::class);

        foreach (['71234567', '712 345 67', '+591 71234567', '+591-71234567', '59171234567'] as $phone) {
            $this->assertSame('59171234567', $service->normalizePhone($phone));
        }

        $this->post(route('investments.advisors.public.store', $firstAdvisor->public_token), $this->payload('Primer registro', '71234567'))
            ->assertRedirect();
        $prospect = InvestmentProspect::firstOrFail();

        $response = $this->followingRedirects()->post(
            route('investments.advisors.public.store', $secondAdvisor->public_token),
            $this->payload('Intento duplicado', '+591-71234567')
        );

        $response->assertOk()
            ->assertSee('Registro encontrado')
            ->assertSee('Ya existe un registro asociado a este número.')
            ->assertDontSee($prospect->prospect_number)
            ->assertDontSee($firstAdvisor->full_name);
        $this->assertSame(1, InvestmentProspect::count());
        $this->assertSame($firstAdvisor->id, $prospect->refresh()->original_advisor_id);
        $this->assertSame($firstAdvisor->id, $prospect->current_advisor_id);
        $this->assertSame('captured', $prospect->status);
        $this->assertSame(2, InvestmentCrmSequence::firstWhere('key', 'investment_prospect')->next_number);
    }

    public function test_success_uses_post_redirect_get_and_audit_avoids_phone_and_qr_token(): void
    {
        $advisor = $this->advisor();

        $response = $this->followingRedirects()->post(
            route('investments.advisors.public.store', $advisor->public_token),
            $this->payload()
        );

        $prospect = InvestmentProspect::firstOrFail();
        $response->assertOk()
            ->assertSee('REGISTRO REALIZADO')
            ->assertSee($prospect->prospect_number)
            ->assertDontSee($prospect->public_id);

        $audit = AuditLog::firstWhere('action', 'investment_prospect_captured');
        $this->assertNotNull($audit);
        $metadata = json_encode($audit->metadata);
        $this->assertStringContainsString($prospect->prospect_number, $metadata);
        $this->assertStringNotContainsString($prospect->phone, $metadata);
        $this->assertStringNotContainsString($advisor->public_token, $metadata);
    }

    public function test_administrative_list_and_detail_require_permission_and_show_local_capture_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 02:48:00', 'UTC'));
        $advisor = $this->advisor();
        $this->capture($advisor, $this->payload('Prospecto Visible', '70003000'));
        $prospect = InvestmentProspect::firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('investments.prospects.index'))
            ->assertOk()
            ->assertSee('Prospecto Visible')
            ->assertSee('Acciones de interés')
            ->assertSee('06/09/2026 22:48');
        $this->get(route('investments.prospects.show', $prospect))
            ->assertOk()
            ->assertSee('06/09/2026 - 22:48')
            ->assertSee('QR del asesor')
            ->assertDontSee($prospect->capture_ip)
            ->assertDontSee((string) $prospect->capture_user_agent)
            ->assertDontSee($prospect->public_id);
        $this->assertSame('2026-09-07 02:48:00', $prospect->captured_at->utc()->format('Y-m-d H:i:s'));

        $unauthorized = User::create([
            'name' => 'Consulta', 'email' => 'prospect-no-access@test.local', 'role' => 'consulta',
            'user_type' => 'internal', 'is_active' => true, 'password' => Hash::make('secret'),
        ]);
        $this->actingAs($unauthorized)->get(route('investments.prospects.index'))->assertForbidden();
        $this->get(route('investments.prospects.show', $prospect))->assertForbidden();
    }

    public function test_combined_advisor_status_and_capture_date_filters_use_captured_at(): void
    {
        $advisorA = $this->advisor('ASE-0001', 'Asesor A');
        $advisorB = $this->advisor('ASE-0002', 'Asesor B');

        Carbon::setTestNow('2026-09-01 09:00:00');
        $this->capture($advisorA, $this->payload('Fuera por fecha', '70000001'));
        Carbon::setTestNow('2026-09-15 10:00:00');
        $this->capture($advisorA, $this->payload('Dentro del filtro', '70000002'));
        Carbon::setTestNow('2026-09-20 11:00:00');
        $this->capture($advisorB, $this->payload('Fuera por asesor', '70000003'));
        InvestmentProspect::where('full_name', 'Fuera por asesor')->update(['status' => 'closed']);

        $this->actingAs($this->admin)->get(route('investments.prospects.index', [
            'advisor_id' => $advisorA->id,
            'status' => 'captured',
            'from' => '2026-09-10',
            'to' => '2026-09-30',
        ]))->assertOk()
            ->assertSee('Dentro del filtro')
            ->assertDontSee('Fuera por fecha')
            ->assertDontSee('Fuera por asesor');
    }

    public function test_public_post_keeps_web_csrf_and_ten_per_minute_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('investments.advisors.public.store');
        $middleware = $route->gatherMiddleware();

        $this->assertContains('web', $middleware);
        $this->assertContains('throttle:10,1', $middleware);
        $this->assertSame(['POST'], $route->methods());
    }

    public function test_public_capture_rate_limit_blocks_abuse_without_blocking_form_view(): void
    {
        $advisor = $this->advisor();
        $url = route('investments.advisors.public.store', $advisor->public_token);

        foreach (range(1, 10) as $attempt) {
            $this->post($url, $this->payload("Prospecto {$attempt}", '7001'.str_pad((string) $attempt, 4, '0', STR_PAD_LEFT)))
                ->assertRedirect();
        }

        $this->post($url, $this->payload('Prospecto limitado', '79999999'))->assertTooManyRequests();
        $this->get(route('investments.advisors.public', $advisor->public_token))->assertOk();
    }

    public function test_manager_has_read_only_crm_access_and_cash_roles_have_none_by_default(): void
    {
        $advisor = $this->advisor();
        $this->capture($advisor, $this->payload());
        $prospect = InvestmentProspect::firstOrFail();
        $manager = $this->internalUser('gerente', 'prospect-manager@test.local');
        $cashDesk = $this->internalUser('caja', 'prospect-cash@test.local');
        $cashier = $this->internalUser('cajero', 'prospect-cashier@test.local');

        $this->assertTrue($manager->hasPermission('investment_prospects.view'));
        $this->assertFalse($manager->hasPermission('investment_prospects.create'));
        $this->assertTrue($manager->hasPermission('investment_prospects.update'));
        $this->assertTrue($manager->hasPermission('investment_prospects.reassign'));
        $this->actingAs($manager)->get(route('investments.prospects.index'))->assertOk()->assertSee('Prospectos');
        $this->get(route('investments.prospects.show', $prospect))->assertOk();

        $this->assertFalse($cashDesk->hasPermission('investment_prospects.view'));
        $this->assertFalse($cashier->hasPermission('investment_prospects.view'));
        $this->actingAs($cashDesk)->get(route('investments.prospects.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('investments.prospects.index'))->assertForbidden();

        $this->assertTrue($this->admin->hasPermission('investment_prospects.view'));
        $this->assertTrue($this->admin->hasPermission('investment_prospects.create'));
        $this->assertTrue($this->admin->hasPermission('investment_prospects.update'));
    }

    private function advisor(string $number = 'ASE-0001', string $name = 'Asesor QR'): InvestmentAdvisor
    {
        return InvestmentAdvisor::create([
            'advisor_number' => $number,
            'public_id' => (string) Str::uuid(),
            'public_token' => Str::random(64),
            'full_name' => $name,
            'phone' => '70000000',
            'email' => strtolower(str_replace(' ', '.', $number)).'@example.test',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
    }

    private function capture(InvestmentAdvisor $advisor, array $payload): void
    {
        app(InvestmentProspectService::class)->createFromAdvisorQr($advisor, $payload, '192.0.2.20', 'Test Browser');
    }

    private function payload(string $name = 'Juan Prospecto', string $phone = '71234567'): array
    {
        return [
            'full_name' => $name,
            'phone' => $phone,
            'requested_shares' => 10,
            'preferred_contact_method' => 'whatsapp',
        ];
    }

    private function internalUser(string $role, string $email): User
    {
        return User::create([
            'name' => str($role)->headline()->toString(),
            'email' => $email,
            'role' => $role,
            'user_type' => 'internal',
            'is_active' => true,
            'password' => Hash::make('secret'),
        ]);
    }
}
