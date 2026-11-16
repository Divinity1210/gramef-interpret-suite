<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RoleHelper
{
    /**
     * Check if user has a specific role using optimized database query
     * 
     * @param \App\User $user
     * @param string $role
     * @return bool
     */
    public static function hasRole($user, $role)
    {
        if (!$user) {
            return false;
        }

        // Cache user roles for 5 minutes to avoid repeated database queries
        $cacheKey = "user_roles_{$user->id}";
        
        $userRoles = Cache::remember($cacheKey, 300, function () use ($user) {
            return DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->pluck('roles.name')
                ->toArray();
        });

        return in_array($role, $userRoles);
    }

    /**
     * Check if user has any of the specified roles
     * 
     * @param \App\User $user
     * @param string|array $roles
     * @return bool
     */
    public static function hasAnyRole($user, $roles)
    {
        if (!$user) {
            return false;
        }

        if (is_string($roles)) {
            $roles = explode('|', $roles);
        }

        // Cache user roles for 5 minutes to avoid repeated database queries
        $cacheKey = "user_roles_{$user->id}";
        
        $userRoles = Cache::remember($cacheKey, 300, function () use ($user) {
            return DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->pluck('roles.name')
                ->toArray();
        });

        return !empty(array_intersect($roles, $userRoles));
    }

    /**
     * Clear user role cache
     * 
     * @param \App\User $user
     */
    public static function clearUserRoleCache($user)
    {
        if ($user) {
            Cache::forget("user_roles_{$user->id}");
        }
    }
}