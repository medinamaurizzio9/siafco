<?php

namespace App\Providers;

use App\Models\InstitutionalSetting;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\QueryException;
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
        foreach (['store.view', 'store.manage-products', 'store.manage-settings', 'store.manage-shipping', 'store.manage-coupons', 'store.manage-orders'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->isInternal() && $user->hasPermission($permission));
        }

        $institution = $this->hasInstitutionalSettingsTable()
            ? InstitutionalSetting::current()
            : InstitutionalSetting::fallback();

        View::share('institution', $institution);
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
