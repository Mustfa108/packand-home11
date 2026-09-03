<?php

namespace App\Providers;

use App\Models\Assessment;
use App\Policies\AssessmentPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Register Policies
        Gate::policy(Assessment::class, AssessmentPolicy::class);

        // Rate Limiting
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'لقد تجاوزت الحد المسموح به من المحاولات. يرجى الانتظار دقيقة.',
                ], 429));
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'لقد تجاوزت الحد المسموح به من الطلبات. يرجى الانتظار.',
                ], 429));
        });
    }
}
