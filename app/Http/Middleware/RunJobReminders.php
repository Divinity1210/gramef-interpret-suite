<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunJobReminders
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (env('ENABLE_REQUEST_REMINDERS', false) && $request->isMethod('get')) {
                $lock = Cache::lock('reminders:run', 60);
                if ($lock->get()) {
                    try {
                        Artisan::call('send-interpreterjob-reminders');
                    } catch (\Throwable $e) {
                        Log::warning('Request-triggered reminders failed: ' . $e->getMessage());
                    } finally {
                        try { $lock->release(); } catch (\Throwable $e) {}
                    }
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking; never fail the request due to reminder runner
        }

        return $next($request);
    }
}
