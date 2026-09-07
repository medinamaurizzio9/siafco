<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\InvestmentAdvisor;
use App\Models\InvestmentAdvisorAccess;
use App\Models\Investor;
use App\Models\Person;
use App\Models\User;
use App\Services\InvestmentAdvisorAccessService;
use App\Services\InvestmentProspectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvestmentAdvisorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_advisor_gets_independent_one_time_credentials_without_domain_accounts(): void
    {
        $admin=$this->user(); $this->actingAs($admin)->post(route('investments.advisors.store'),['full_name'=>'Carlos Pérez','phone'=>'71234567','email'=>null,'user_id'=>null,'is_active'=>true])->assertRedirect()->assertSessionHas('crm_credentials');
        $advisor=InvestmentAdvisor::firstOrFail(); $access=$advisor->access;
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{6}$/',$access->login_code);
        $credentials=session('crm_credentials'); $this->assertMatchesRegularExpression('/^\d{6}$/',$credentials['pin']); $this->assertTrue(Hash::check($credentials['pin'],$access->pin_hash)); $this->assertNotSame($credentials['pin'],$access->pin_hash); $this->assertTrue($access->must_change_pin);
        $this->assertSame(1,User::count()); $this->assertSame(0,Person::count()); $this->assertSame(0,Affiliate::count()); $this->assertSame(0,Investor::count());
    }

    public function test_existing_advisor_can_generate_reset_disable_enable_and_unlock_access(): void
    {
        $advisor=$this->advisor(); $service=app(InvestmentAdvisorAccessService::class); $first=$service->createAccess($advisor); $oldHash=$first['access']->pin_hash;
        $this->assertSame(1,InvestmentAdvisorAccess::count()); $reset=$service->resetPin($first['access']); $this->assertNotSame($oldHash,$reset['access']->fresh()->pin_hash); $this->assertTrue($reset['access']->fresh()->must_change_pin);
        $service->disable($reset['access']); $this->assertFalse($reset['access']->fresh()->is_enabled); $service->enable($reset['access']); $this->assertTrue($reset['access']->fresh()->is_enabled);
        $reset['access']->update(['failed_attempts'=>5,'locked_until'=>now()->addMinutes(15)]); $service->unlock($reset['access']); $this->assertSame(0,$reset['access']->fresh()->failed_attempts); $this->assertNull($reset['access']->fresh()->locked_until);
    }

    public function test_login_is_case_insensitive_for_code_forces_pin_change_and_uses_separate_session(): void
    {
        [$advisor,$access,$pin]=$this->credential();
        $this->post(route('investment-crm.advisor.login.store'),['login_code'=>strtolower($access->login_code),'pin'=>$pin])->assertRedirect(route('investment-crm.advisor.pin.edit'))->assertSessionHas('investment_crm_access_id',$access->id);
        $this->get(route('investment-crm.advisor.dashboard'))->assertRedirect(route('investment-crm.advisor.pin.edit'));
        $this->post(route('investment-crm.advisor.pin.update'),['current_pin'=>$pin,'pin'=>'654321','pin_confirmation'=>'654321'])->assertRedirect(route('investment-crm.advisor.dashboard'));
        $access->refresh(); $this->assertFalse($access->must_change_pin); $this->assertNotNull($access->pin_changed_at); $this->assertTrue(Hash::check('654321',$access->pin_hash)); $this->assertFalse(Hash::check($pin,$access->pin_hash)); $this->assertGuest();
    }

    public function test_five_failures_lock_access_and_success_after_expiration_resets_counter(): void
    {
        [$advisor,$access,$pin]=$this->credential();
        for($i=0;$i<5;$i++) $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>'000000']);
        $this->assertSame(5,$access->fresh()->failed_attempts); $this->assertNotNull($access->fresh()->locked_until);
        $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>$pin])->assertTooManyRequests();
        $this->travel(16)->minutes(); $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>$pin])->assertRedirect();
        $this->assertSame(0,$access->fresh()->failed_attempts); $this->assertNotNull($access->fresh()->last_login_at);
    }

    public function test_portal_is_scoped_returns_not_found_for_foreign_prospect_and_disabled_access_is_evicted(): void
    {
        [$a,$access,$pin]=$this->credential(false); [$b]=$this->credential(false,'ASE-0002'); $own=$this->prospect($a,'70000001'); $other=$this->prospect($b,'70000002');
        $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>$pin])->assertRedirect(route('investment-crm.advisor.dashboard'));
        $this->get(route('investment-crm.advisor.dashboard'))->assertOk()->assertSee($a->full_name);
        $this->get(route('investment-crm.advisor.prospects'))->assertOk()->assertSee($own->prospect_number)->assertDontSee($other->prospect_number);
        $this->get(route('investment-crm.advisor.prospects.show',$other))->assertNotFound();
        $access->update(['is_enabled'=>false]); $this->get(route('investment-crm.advisor.dashboard'))->assertRedirect(route('investment-crm.advisor.login'))->assertSessionMissing('investment_crm_access_id');
    }

    public function test_logout_removes_only_crm_session(): void
    {
        [$advisor,$access,$pin]=$this->credential(false); $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>$pin]); $this->post(route('investment-crm.advisor.logout'))->assertRedirect(route('investment-crm.advisor.login'))->assertSessionMissing('investment_crm_access_id');
    }

    public function test_mobile_kanban_exposes_accessible_tabs_search_actions_fab_and_active_navigation(): void
    {
        [$advisor, $access] = $this->credential(false);
        $prospect = $this->prospect($advisor, '70000009');

        $this->withSession(['investment_crm_access_id' => $access->id])
            ->get(route('investment-crm.advisor.kanban'))
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('data-stage-tab="captured"', false)
            ->assertSee('data-kanban-search', false)
            ->assertSee('Registrar interacción')
            ->assertSee('Cambiar etapa')
            ->assertSee('Nuevo prospecto')
            ->assertSee('aria-current="page"', false)
            ->assertSee($prospect->prospect_number);
    }

    public function test_authenticated_advisor_profile_shows_existing_personal_qr_and_public_url_without_exposing_token_as_text(): void
    {
        Storage::fake('public');
        [$advisor, $access] = $this->credential(false);
        app(\App\Services\InvestmentAdvisorService::class)->generateQr($advisor);
        $token = $advisor->public_token;
        $hash = hash_file('sha256', Storage::disk('public')->path($advisor->qrPath()));

        $response = $this->withSession(['investment_crm_access_id' => $access->id])
            ->get(route('investment-crm.advisor.profile'));

        $response->assertOk()
            ->assertSee('Mi QR de captación')
            ->assertSee(route('investments.advisors.public', $token), false)
            ->assertSee(route('investment-crm.advisor.qr.download'), false)
            ->assertDontSee('>'.$token.'<', false);
        $this->assertSame($token, $advisor->fresh()->public_token);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('public')->path($advisor->qrPath())));
    }

    public function test_qr_download_is_scoped_to_authenticated_advisor_and_does_not_accept_another_advisor_id(): void
    {
        Storage::fake('public');
        [$advisor, $access] = $this->credential(false);
        [$other] = $this->credential(false, 'ASE-0002');
        Storage::disk('public')->put($advisor->qrPath(), "\x89PNG\r\n\x1a\nown");
        Storage::disk('public')->put($other->qrPath(), "\x89PNG\r\n\x1a\nother");

        $response = $this->withSession(['investment_crm_access_id' => $access->id])
            ->get(route('investment-crm.advisor.qr.download', ['advisor_id' => $other->id]));

        $response->assertOk()->assertDownload("QR-{$advisor->advisor_number}.png");
        $this->assertSame("\x89PNG\r\n\x1a\nown", $response->streamedContent());
    }

    public function test_missing_qr_is_regenerated_without_changing_public_token(): void
    {
        Storage::fake('public');
        [$advisor, $access] = $this->credential(false);
        $token = $advisor->public_token;

        $this->withSession(['investment_crm_access_id' => $access->id])
            ->get(route('investment-crm.advisor.profile'))
            ->assertOk()
            ->assertSee('Mi QR de captación');

        Storage::disk('public')->assertExists($advisor->qrPath());
        $this->assertSame($token, $advisor->fresh()->public_token);
    }

    public function test_login_is_generic_validates_pin_and_rejects_disabled_or_inactive_advisor(): void
    {
        [$advisor,$access,$pin]=$this->credential(false);
        $this->post(route('investment-crm.advisor.login.store'),['login_code'=>'ZZZZZZ','pin'=>'123456'])->assertSessionHasErrors(['login_code'=>'Código o PIN incorrecto.']);
        $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>'12345'])->assertSessionHasErrors('pin');
        $access->update(['is_enabled'=>false]); $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>$pin])->assertSessionHasErrors(['login_code'=>'Código o PIN incorrecto.']);
        $access->update(['is_enabled'=>true]); $advisor->update(['is_active'=>false]); $this->post(route('investment-crm.advisor.login.store'),['login_code'=>$access->login_code,'pin'=>$pin])->assertSessionHasErrors(['login_code'=>'Código o PIN incorrecto.']);
        $this->assertGuest();
    }

    private function credential(bool $mustChange=true,string $number='ASE-0001'): array { $a=$this->advisor($number); $r=app(InvestmentAdvisorAccessService::class)->createAccess($a); if(!$mustChange)$r['access']->update(['must_change_pin'=>false]); return [$a,$r['access']->fresh(),$r['pin']]; }
    private function advisor(string $number='ASE-0001'): InvestmentAdvisor { return InvestmentAdvisor::create(['advisor_number'=>$number,'public_id'=>(string)Str::uuid(),'public_token'=>Str::random(64),'full_name'=>$number,'phone'=>'71111111','is_active'=>true]); }
    private function prospect(InvestmentAdvisor $a,string $phone){ return app(InvestmentProspectService::class)->createFromAdvisorQr($a,['full_name'=>'Prospecto '.$phone,'phone'=>$phone,'requested_shares'=>10,'preferred_contact_method'=>'whatsapp'],'127.0.0.1','Test')['prospect']; }
    private function user(): User { return User::create(['name'=>'Admin','email'=>'access-admin@test.local','role'=>'superadministrador','user_type'=>'internal','is_active'=>true,'password'=>Hash::make('secret')]); }
}
