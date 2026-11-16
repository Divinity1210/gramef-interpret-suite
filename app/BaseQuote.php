<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\RoleHelper;

class BaseQuote extends Model
{
    protected const STATUSES = [
        0 => 'quote sent',
        1 => 'quote accepted',
        2 => 'quote rejected',
    ];

    protected $appends = [
        'totalAmount'
    ];

    public function getStatusAttribute()
    {
        $raw = $this->attributes['status'] ?? null;
        if (is_numeric($raw)) {
            $id = (int) $raw;
            return self::STATUSES[$id] ?? self::STATUSES[0];
        }
        if (is_string($raw)) {
            return $raw;
        }
        return self::STATUSES[0];
    }

    public function getStatusIdAttribute()
    {
        $raw = $this->attributes['status'] ?? null;
        if (is_numeric($raw)) {
            return (int) $raw;
        }
        $map = array_flip(self::STATUSES);
        return $map[$raw] ?? null;
    }

    public function getStatusForUser()
    {
        return RoleHelper::hasRole(auth()->user(), 'client') && $this->status == 'quote sent' ? 
            'quoted recieved' : 
            $this->status;
    }
}
