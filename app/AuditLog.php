<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * The user the action was performed on behalf of (subject).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The actor who performed the action.
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}