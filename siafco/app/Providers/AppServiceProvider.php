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
use App\Models\InvestmentProspect;
use App\Models\SimpleInvestmentContract;
use App\Models\SimpleInvestmentInvestor;
use App\Models\User;
use App\Policies\InvestmentProspectPolicy;
use App\Policies\SimpleInvestmentContractPolicy;
use App\Policies\SimpleInvestmentInvestorPolicy;
use App\Policies\UserPolicy;
use App\Services\InvestmentCrmSettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(InvestmentProspect::class, InvestmentProspectPolicy::class);
        Gate::policy(SimpleInvestmentInvestor::class, SimpleInvestmentInvestorPolicy::class);
        Gate::policy(SimpleInvestmentContract::class, SimpleInvestmentContractPolicy::class);
        $this->registerPublicAffiliationRateLimiters();
        RateLimiter::for('simple-investor-login', fn (Request $request) => Limit::perMinute(5)
            ->by('simple-investor:'.hash('sha256', $request->ip().'|'.mb_strtoupper(trim((string) $request->input('ci'))))));
        $this->registerDomainListeners();
        foreach (['store.view', 'store.manage-products', 'store.manage-settings', 'store.manage-shipping', 'store.manage-coupons', 'store.manage-orders', 'store.verify-receipts'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->isInternal() && $user->hasPermission($permission));
        }

        $institution = $this->hasInstitutionalSettingsTable()
            ? InstitutionalSetting::current()
            : InstitutionalSetting::fallback();

        View::share('institution', $institution);
        View::composer([
            'investments.advisors.public',
            'investment-crm.advisor.*',
            'components.investment-crm.advisor.layout',
        ], function ($view) {
            $view->with('settings', app(InvestmentCrmSettingsService::class)->all());
        });
    }

    private function registerPublicAffiliationRateLimiters(): void
    {
        RateLimiter::for('public-affiliation-read', fn (Request $request) => Limit::perMinute(30)
            ->by('public-affiliation:read:'.$request->ip()));

        RateLimiter::for('public-affiliation-register', fn (Request $request) => Limit::perMinute(5)
            ->by('public-affiliation:register:'.$request->ip()));

        RateLimiter::for('public-affiliation-payment', function (Request $request) {
            $application = $request->route('application');
            $applicationKey = is_object($application) && method_exists($application, 'getRouteKey')
                ? $application->getRouteKey()
                : (string) $application;

            return Limit::perMinute(5)
                ->by('public-affiliation:payment:'.$request->ip().':'.$applicationKey);
        });
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
