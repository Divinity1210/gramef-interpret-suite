<?php

namespace App\Providers;

use App\User;
use App\Agent;
use App\InterpreterJob;
use App\MatchedAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Observers\UserObserver;
use App\Observers\AgentObserver;
use App\Observers\InterpreterJobObserver;
use App\Observers\MatchedAgentObserver;
use App\Observers\TranslatorJobObserver;
use App\TranslatorJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        User::observe(UserObserver::class);
        Agent::observe(AgentObserver::class);
        InterpreterJob::observe(InterpreterJobObserver::class);
        TranslatorJob::observe(TranslatorJobObserver::class);
        MatchedAgent::observe(MatchedAgentObserver::class);

        // Automatically clear compiled Blade views once when a new zip update is extracted on cPanel (no terminal required)
        try {
            $viewClearStamp = storage_path('framework/views/.cleared_20261105_v2');
            if (!is_file($viewClearStamp)) {
                foreach ((array) glob(storage_path('framework/views/*.php')) as $compiledView) {
                    if (is_file($compiledView)) {
                        @unlink($compiledView);
                    }
                }
                @file_put_contents($viewClearStamp, date('c'));
            }
        } catch (\Throwable $e) {
            // Ignore any filesystem edge cases
        }

        // Disable Laravel Debugbar in local environment to rule out UI overlay issues on guest pages
        try {
            if (app()->environment('local') && class_exists(\Barryvdh\Debugbar\Facade::class)) {
                // If the user is not authenticated, disable the debugbar to avoid any possible overlay
                if (!Auth::check()) {
                    \Barryvdh\Debugbar\Facade::disable();
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore if Debugbar is not installed or any error occurs
        }

        // Force HTTPS behind reverse proxies (e.g., Railway)
        try {
            if (
                str_starts_with((string) config('app.url'), 'https://') ||
                request()->header('X-Forwarded-Proto') === 'https' ||
                app()->environment('production')
            ) {
                URL::forceScheme('https');
            }
        } catch (\Throwable $e) {
            // Safe fallback in CLI contexts
        }

        // Share active White-Label Tenant Brand globally across all views
        View::composer('*', function ($view) {
            static $cachedBrand = null;
            if ($cachedBrand === null) {
                $defaultBrand = (object) [
                    'slug' => 'gramef',
                    'brand_name' => 'GRAMEF InterpretFlow',
                    'brand_tagline' => 'Enterprise Language Operations, Automated Billing & Credit Control',
                    'logo_text' => 'GRAMEF',
                    'primary_color' => '#2563EB',
                    'accent_color' => '#10B981',
                    'currency_symbol' => '£',
                    'default_vat_rate' => 20.00,
                    'chaser_mode' => 'review',
                    'active_delivery_stage' => 4,
                ];
                try {
                    if (class_exists(\App\TenantSetting::class) && Schema::hasTable('tenant_settings')) {
                        $active = \App\TenantSetting::where('is_active', true)->first();
                        $cachedBrand = $active ?: $defaultBrand;
                    } else {
                        $cachedBrand = $defaultBrand;
                    }
                } catch (\Throwable $e) {
                    $cachedBrand = $defaultBrand;
                }
            }
            $view->with('tenantBrand', $cachedBrand);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        UploadedFile::macro('hashNameWithExtension', function (string $extension, string $path = null) {
            if ($path) {
                $path = rtrim($path, '/').'/';
            }
            // Use a generated random hash rather than relying on an undefined property.
            // This avoids linter/runtime errors about $this->hashName not existing on UploadedFile.
            $hash = Str::random(40);

            return $path.$hash.'.'.$extension;
        });
    }
}
