<?php

namespace App\Http\Middleware;

use App\Helpers\RoleHelper;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            $user = Auth::user();
            
            // Use optimized RoleHelper to check for 'new-agent' role
            return RoleHelper::hasRole($user, 'new-agent') ? redirect('/agent/profile/create') : redirect('/interpreter-jobs');
        }

        return $next($request);
    }
}
