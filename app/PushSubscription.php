<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'user_id',
        'endpoint',
        'p256dh_key',
        'auth_key',
        'platform',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
