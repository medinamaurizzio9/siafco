<?php

namespace App\Providers;

use App\Models\InstitutionalSetting;
use Illuminate\Support\ServiceProvider;
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
        $institution = Schema::hasTable('institutional_settings')
            ? InstitutionalSetting::current()
            : InstitutionalSetting::fallback();

        View::share('institution', $institution);
    }
}
