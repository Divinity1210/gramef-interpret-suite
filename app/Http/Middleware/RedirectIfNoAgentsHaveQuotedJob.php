<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\RoleHelper;

class RedirectIfNoAgentsHaveQuotedJob
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
        $job = $request->route('interpreterJob') ?? $request->route('translatorJob');

        if (!RoleHelper::hasRole(auth()->user(), 'agent') && !$job->quotedMatchedAgents()->exists()) {
            return redirect()->back()->withErrors(['No agents have accepted this job']);
        }

        return $next($request);
    }
}
