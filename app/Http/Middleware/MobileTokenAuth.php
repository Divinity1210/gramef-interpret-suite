<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;

class MobileTokenAuth
{
    public function handle($request, Closure $next)
    {
        $token = null;
        try {
            $token = $request->bearerToken();
        } catch (\Throwable $e) {
            $token = null;
        }

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $hashedToken = hash('sha256', $token);

        // AuthController stores hash('sha256', $token) in remember_token
        $user = User::where('remember_token', $hashedToken)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}

