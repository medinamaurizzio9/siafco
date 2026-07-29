<?php

namespace App\Providers;

use App\Models\InstitutionalSetting;
use App\Models\User;
use App\Policies\UserPolicy;
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

        $institution = Schema::hasTable('institutional_settings')
            ? InstitutionalSetting::current()
            : InstitutionalSetting::fallback();

        View::share('institution', $institution);
    }
}
