<?php

namespace App\Http\Middleware;

use App\Helpers\RoleHelper;
use Closure;

class RedirectNewAgentToProfile
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
        $user = $request->user();
        
        // Use optimized RoleHelper to check for 'new-agent' role
        if (RoleHelper::hasRole($user, 'new-agent')) {
            return redirect()->route('agents.profile.create');
        }

        return $next($request);
    }
}
