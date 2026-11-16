<?php
namespace App\Traits;

use App\Helpers\RoleHelper;
use App\Models\Role;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait DeterminesUserType
{
    public function getUserType($userId)
    {
        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        // Use optimized RoleHelper to get user roles with caching
        $cacheKey = "user_roles_{$userId}";
        $userRoles = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($user) {
            return \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_id', $user->id)
                ->where('model_has_roles.model_type', get_class($user))
                ->pluck('roles.name')
                ->toArray();
        });

        return !empty($userRoles) ? $userRoles[0] : null;
    }
}
