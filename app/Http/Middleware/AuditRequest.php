<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\AuditLog;

class AuditRequest
{
    /**
     * Handle an incoming request and record an audit trail entry.
     *
     * We log route name, URI, method, response status, user agent, and the keys of query/body params.
     * Sensitive fields are excluded. Errors are swallowed to avoid impacting user requests.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip obvious static assets to reduce noise
        if ($request->is('favicon.ico', 'robots.txt', 'assets/*', 'css/*', 'js/*', 'images/*', 'storage/*')) {
            return $next($request);
        }

        $response = null;
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Attempt to log exceptions without impacting user flow visibility
            try {
                if (config('app.audit_requests', false)) {
                    $actorId = Auth::id();
                    AuditLog::create([
                        'user_id' => $actorId,
                        'actor_id' => $actorId,
                        'action' => 'error',
                        'entity_type' => 'http',
                        'entity_id' => null,
                        'metadata' => [
                            'uri' => $request->path(),
                            'method' => $request->method(),
                            'message' => $e->getMessage(),
                            'code' => method_exists($e, 'getCode') ? $e->getCode() : null,
                        ],
                        'ip_address' => $request->ip(),
                    ]);
                }
            } catch (\Throwable $ignored) {}

            throw $e; // Preserve original exception behavior
        }

        try {
            if (!config('app.audit_requests', false)) {
                return $response;
            }

            $route = $request->route();
            $actorId = Auth::id();

            $metadata = [
                'route_name' => $route ? $route->getName() : null,
                'action' => ($route && method_exists($route, 'getActionName')) ? $route->getActionName() : null,
                'uri' => $request->path(),
                'method' => $request->method(),
                'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'user_agent' => substr((string)($request->userAgent() ?? ''), 0, 255),
                'query_keys' => array_keys($request->query()),
                'body_keys' => array_keys($request->except(['password', 'password_confirmation', '_token', '_method'])),
            ];

            // Capture redirect target if applicable
            if ($response instanceof \Illuminate\Http\RedirectResponse) {
                $metadata['is_redirect'] = true;
                $metadata['redirect_to'] = $response->getTargetUrl();
            }

            AuditLog::create([
                'user_id' => $actorId,
                'actor_id' => $actorId,
                'action' => 'request',
                'entity_type' => 'http',
                'entity_id' => null,
                'metadata' => $metadata,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // Do not break user flow if auditing fails
        }

        return $response;
    }
}