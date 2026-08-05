<?php

namespace App\Providers;

use App\Events\AffiliateAccessBlocked;
use App\Events\AffiliateAccessEnabled;
use App\Events\AffiliateActivated;
use App\Events\CredentialActivated;
use App\Events\CredentialCreated;
use App\Events\CredentialRevoked;
use App\Events\PaymentConfirmed;
use App\Events\PaymentRejected;
use App\Events\PaymentVoided;
use App\Listeners\CreateAuditEntryListener;
use App\Listeners\DispatchFutureNotificationListener;
use App\Listeners\GenerateCredentialListener;
use App\Listeners\RefreshAffiliateCapabilitiesListener;
use App\Listeners\UpdateDashboardStatisticsListener;
use App\Models\InstitutionalSetting;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        $this->registerDomainListeners();

        $institution = $this->hasInstitutionalSettingsTable()
            ? InstitutionalSetting::current()
            : InstitutionalSetting::fallback();

        View::share('institution', $institution);
    }

    private function registerDomainListeners(): void
    {
        foreach ([PaymentConfirmed::class, PaymentRejected::class, PaymentVoided::class] as $event) {
            Event::listen($event, CreateAuditEntryListener::class);
            Event::listen($event, UpdateDashboardStatisticsListener::class);
            Event::listen($event, RefreshAffiliateCapabilitiesListener::class);
            Event::listen($event, DispatchFutureNotificationListener::class);
        }

        Event::listen(AffiliateActivated::class, CreateAuditEntryListener::class);
        Event::listen(AffiliateActivated::class, UpdateDashboardStatisticsListener::class);
        Event::listen(AffiliateActivated::class, RefreshAffiliateCapabilitiesListener::class);
        Event::listen(AffiliateActivated::class, GenerateCredentialListener::class);

        foreach ([CredentialCreated::class, CredentialActivated::class, CredentialRevoked::class] as $event) {
            Event::listen($event, CreateAuditEntryListener::class);
            Event::listen($event, RefreshAffiliateCapabilitiesListener::class);
            Event::listen($event, DispatchFutureNotificationListener::class);
        }

        foreach ([AffiliateAccessBlocked::class, AffiliateAccessEnabled::class] as $event) {
            Event::listen($event, CreateAuditEntryListener::class);
            Event::listen($event, RefreshAffiliateCapabilitiesListener::class);
        }
    }

    private function hasInstitutionalSettingsTable(): bool
    {
        try {
            return Schema::hasTable('institutional_settings');
        } catch (QueryException $exception) {
            if ($this->app->runningInConsole() && $this->isConsoleDatabaseBootstrapException($exception)) {
                return false;
            }

            throw $exception;
        }
    }

    private function isConsoleDatabaseBootstrapException(QueryException $exception): bool
    {
        $previousCode = (string) ($exception->getPrevious()?->getCode() ?? '');
        $message = $exception->getMessage();

        return in_array($previousCode, ['1049', '2002', '2006'], true)
            || str_contains($message, 'Unknown database')
            || str_contains($message, 'Connection refused');
    }
}
