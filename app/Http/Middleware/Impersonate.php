<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Impersonate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->session()->has('impersonate')) {
            try {
                $impersonateUserId = $request->session()->get('impersonate');
                
                // Attempt to authenticate as the impersonated user
                if (!Auth::onceUsingId($impersonateUserId)) {
                    Log::warning('Failed to authenticate impersonated user', [
                        'impersonate_user_id' => $impersonateUserId,
                        'session_id' => $request->session()->getId(),
                        'ip' => $request->ip()
                    ]);
                    
                    // Clear invalid impersonation session
                    $request->session()->forget('impersonate');
                    return redirect()->route('index')->with('error', 'Impersonation session expired. Please try again.');
                }
                
                Log::debug('Impersonation middleware executed successfully', [
                    'impersonate_user_id' => $impersonateUserId,
                    'authenticated_user_id' => Auth::id()
                ]);
                
            } catch (\Exception $e) {
                Log::error('Impersonation middleware error', [
                    'impersonate_user_id' => $request->session()->get('impersonate'),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Clear problematic impersonation session
                $request->session()->forget('impersonate');
                return redirect()->route('index')->with('error', 'An error occurred during impersonation. Please try again.');
            }
        }        
        
        return $next($request);
    }
}
