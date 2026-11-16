<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\RoleHelper;

class RedirectAgentIfJobIsAlreadyAssigned
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

        if (RoleHelper::hasRole($request->user(), 'agent') && $job->assignedMatched()->exists()) {
            return redirect()->back()->withErrors(['This job has already been assigned']);
        }

        return $next($request);
    }
}
