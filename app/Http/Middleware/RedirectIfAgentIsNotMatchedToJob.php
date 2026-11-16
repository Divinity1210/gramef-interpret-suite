<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\RoleHelper;

class RedirectIfAgentIsNotMatchedToJob
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
        $notMatchedToJob = !optional($job->matchedLoggedInAgent())->exists;

        if (RoleHelper::hasRole($request->user(), 'agent') && $notMatchedToJob) {
            return redirect()->back()->withErrors(['You are not matched to this job']);
        }

        return $next($request);
    }
}
