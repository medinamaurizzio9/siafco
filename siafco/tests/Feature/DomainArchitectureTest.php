<?php

namespace Tests\Feature;

use App\Events\AffiliateActivated;
use App\Events\PaymentConfirmed;
use App\Events\PaymentRejected;
use App\Events\PaymentVoided;
use App\Listeners\CreateAuditEntryListener;
use App\Listeners\DispatchFutureNotificationListener;
use App\Listeners\RefreshAffiliateCapabilitiesListener;
use App\Listeners\UpdateDashboardStatisticsListener;
use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\AffiliationPlan;
use App\Models\AuditLog;
use App\Models\DigitalCredential;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\Domain\PaymentConfirmedNotification;
use App\Services\AffiliateTimelineService;
use App\Services\DashboardMetricsService;
use App\Services\NotificationDispatcher;
use App\Services\SiafcoHealthCheckService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DomainArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_events_are_after_commit_and_listeners_are_single_purpose(): void
    {
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new PaymentConfirmed(1, 2, 3, 'confirmed'));
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new PaymentRejected(1, 2, 3, 'rejected'));
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new PaymentVoided(1, 2, 3, 'voided'));
        $this->assertTrue(class_exists(CreateAuditEntryListener::class));
        $this->assertTrue(class_exists(UpdateDashboardStatisticsListener::class));
        $this->assertTrue(class_exists(RefreshAffiliateCapabilitiesListener::class));
        $this->assertTrue(class_exists(DispatchFutureNotificationListener::class));
    }

    public function test_dashboard_metrics_timeline_healthcheck_and_notification_dispatcher(): void
    {
        [$affiliate, $user] = $this->fixture();
        AffiliationPayment::create([
            'affiliate_id' => $affiliate->id,
            'amount' => 120,
            'paid_amount' => 120,
            'currency' => 'BOB',
            'status' => 'confirmed',
        ]);
        DigitalCredential::create([
            'affiliate_id' => $affiliate->id,
            'qr_path' => 'credentials/qr/test.png',
            'generated_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'payment_confirmed',
            'auditable_type' => AffiliationPayment::class,
            'auditable_id' => 1,
            'metadata' => ['affiliate_id' => $affiliate->id],
        ]);

        $metrics = app(DashboardMetricsService::class)->metrics();
        $this->assertSame(1, $metrics['active']);
        $this->assertSame(1, $metrics['confirmedPayments']);
        $this->assertSame(1, $metrics['credentials']);
        Cache::put(DashboardMetricsService::CACHE_KEY, ['stale' => true], 60);
        app(UpdateDashboardStatisticsListener::class)->handle(new AffiliateActivated($affiliate->id));
        $this->assertFalse(Cache::has(DashboardMetricsService::CACHE_KEY));

        $timeline = app(AffiliateTimelineService::class)->forAffiliate($affiliate);
        $this->assertSame('Pago confirmado', $timeline->first()['label']);

        $this->assertSame(0, app(SiafcoHealthCheckService::class)->findings()['active_affiliates_without_credential']);

        $dispatcher = new NotificationDispatcher();
        $dispatcher->dispatch(new PaymentConfirmedNotification(1, $affiliate->id));
        $this->assertCount(1, $dispatcher->queued);
    }

    private function fixture(): array
    {
        $sector = Sector::create(['name' => 'Salud', 'code' => 'SAL', 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => 'Plan', 'type' => 'independiente', 'affiliation_fee' => 100, 'credential_fee' => 20, 'currency' => 'BOB', 'is_active' => true]);
        $person = Person::create(['full_name' => 'AFILIADA TEST', 'ci' => '123']);
        $user = User::factory()->create(['user_type' => 'affiliate', 'role' => 'afiliado']);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'sector_id' => $sector->id,
            'affiliation_plan_id' => $plan->id,
            'full_name' => 'AFILIADA TEST',
            'ci' => '123',
            'email' => $user->email,
            'status' => 'activo',
        ]);

        return [$affiliate, $user];
    }
}
