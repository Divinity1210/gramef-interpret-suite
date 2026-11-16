<?php

namespace App;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'title',
        'first_name',
        'last_name',
        'email',
        'password',
        'enabled',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $appends = ['fullName'];

    public function setPasswordAttribute($password)
    {
        if (!empty($password)) {
            $this->attributes['password'] = bcrypt($password);
        }
    }

    public function agent()
    {
        return $this->hasOne('App\Agent');
    }

    public function client()
    {
        return $this->hasOne('App\Client');
    }

    public function hasNotSeenTerms()
    {
        return false;
        //        return $this->client()->exists() && !$this->client->seen_terms;
    }

    public function getFullNameAttribute() //I'm not sure why this is appended if the method didn't exist
    {
        return $this->getFullName();
    }

    public function getFullName()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getFullNameWithTitle()
    {
        return config('enums.titles')[$this->title] . ' ' . $this->first_name . ' ' . $this->last_name;
    }

    public function getUserLevel()
    {
        // Always derive role names as a Collection to avoid array/collection mismatches
        // Prefer eager-loaded relation to avoid N+1 queries
        if ($this->relationLoaded('roles')) {
            $roleNames = $this->roles instanceof \Illuminate\Support\Collection
                ? $this->roles->pluck('name')
                : collect($this->roles)->map(function ($r) {
                    // Handle array payloads or strings defensively
                    if (is_array($r)) {
                        return $r['name'] ?? null;
                    }
                    if (is_string($r)) {
                        return $r;
                    }
                    return isset($r->name) ? $r->name : null;
                })->filter();
        } else {
            // Use Spatie's API which returns a Collection and hits the cache internally when possible
            // Fallback to DB if trait is unavailable for some reason
            if (method_exists($this, 'getRoleNames')) {
                $roleNames = $this->getRoleNames(); // Collection of strings
            } else {
                $roleNames = collect(\Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('model_has_roles.model_id', $this->id)
                    ->where('model_has_roles.model_type', get_class($this))
                    ->pluck('roles.name')
                    ->toArray());
            }
        }

        $roleNames = collect($roleNames)->filter()->values();

        if ($roleNames->contains('super-admin')) {
            return 'Super Admin';
        }

        // Return first role name if present, else default to 'Admin'
        $first = $roleNames->first();
        return $first ? Str::title(str_replace('-', ' ', $first)) : 'Admin';
    }

    /**
     * Return the user's role names as a Collection of strings.
     * This provides a stable API for static analyzers and callers,
     * wrapping Spatie's getRoleNames() when available.
     */
    public function roleNames()
    {
        if (method_exists($this, 'getRoleNames')) {
            return $this->getRoleNames(); // Collection of strings
        }
        // Fallback: query roles defensively
        return collect(\Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->pluck('roles.name')
            ->toArray());
    }

    /** Convenience helpers for role checks without relying on dynamic trait methods */
    public function isAdmin(): bool
    {
        $roles = $this->roleNames();
        return $roles->contains('admin') || $roles->contains('super-admin');
    }

    public function isAgent(): bool
    {
        return $this->roleNames()->contains('agent');
    }

    public function isClient(): bool
    {
        return $this->roleNames()->contains('client');
    }

    public function scopeHasExpiredDBS($query)
    {
        $query->whereHas('agent', function ($query) {
            $query
                ->where('notified_of_dbs', false)
                ->where('dbs_expiry_date', '<=', today()->addMonths(3));
        });
    }


    public function scopeHasExpiredDBS2($query)
    {
        $query->whereHas('agent', function ($query) {
            $query
                ->where('notified_week_of_dbs', false)
                ->where('dbs_expiry_date', '<=', today()->addWeek());
        });
    }


    public function setImpersonating($id)
    {
        Session::put('impersonate', $id);
    }

    public function stopImpersonating()
    {
        Session::forget('impersonate');
    }

    public function isImpersonating()
    {
        return Session::has('impersonate');
    }


}