<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\RoleHelper;

class RedirectIfAgentHasNotQuotedJob
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
        if (RoleHelper::hasRole($request->user(), 'agent')) {
            $job = $request->route('interpreterJob') ?? $request->route('translatorJob');

            $matched = $job->matchedLoggedInAgent();
            $agentHasNotQuotedForJob = !$matched || $matched->quotes->isEmpty();
            $agentHasNotQuotedForInterpreterJob = $request->route('interpreterJob') && $job->client->always_requires_a_quote && $agentHasNotQuotedForJob;
            $agentHasNotQuotedForTranslatorJob = $request->route('translatorJob') && $agentHasNotQuotedForJob;

            if ($agentHasNotQuotedForInterpreterJob || $agentHasNotQuotedForTranslatorJob) {
                return redirect()->back()->withErrors(['You must provide a quote for this job before accepting it']);
            }
        }

        return $next($request);
    }
}
