<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = [
        'type',
        'format',
        'filters',
        'status',
        'file_path',
        'user_id'
    ];

    protected $casts = [
        'filters' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case 'completed':
                return 'success';
            case 'processing':
                return 'warning';
            case 'failed':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    public function getStatusIconAttribute()
    {
        switch ($this->status) {
            case 'completed':
                return 'fas fa-check-circle';
            case 'processing':
                return 'fas fa-spinner fa-spin';
            case 'failed':
                return 'fas fa-exclamation-circle';
            default:
                return 'fas fa-clock';
        }
    }
}