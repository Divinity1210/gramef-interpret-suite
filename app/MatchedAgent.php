<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MatchedAgent extends Model
{
    protected $fillable = [
        'agent_id',
        'status',
        'distance',
    ];

    private const STATUSES = [
        0 => 'matched',
        1 => 'rejected',
        2 => 'accepted',
        3 => 'cancelled',
        4 => 'assigned',
    ];

    public function getStatusAttribute()
    {
        return self::STATUSES[ $this->attributes['status'] ];
    }

    public function getStatusIdAttribute()
    {
        return $this->attributes['status'];
    }

    public function getFormattedDistance()
    {
        $county = optional($this->agent)->county ?: 'N.A';
        return $this->distance ?
            "$county / {$this->distance} miles" :
            "$county/ N.A";
    }

    public function interpreterJobs()
    {
        return $this->belongsTo('App\InterpreterJob', 'job_id');
    }

    public function translatorJobs()
    {
        return $this->belongsTo('App\TranslatorJob', 'job_id');
    }

    public function job()
    {
        return $this->morphTo();
    }

    public function agent()
    {
        return $this->belongsTo('App\Agent');
    }

    public function quotes()
    {
        return $this->hasMany('App\Quote');
    }

    public function cancellation()
    {
        return $this->hasOne('App\Cancellation');
    }

    public function latestQuote()
    {
        return $this->quotes->first();
    }

    public function canBeAssigned()
    {
        if ((int) data_get($this->job, 'agent_id', 0) > 0) {
            return false;
        }

        $assignedMatched = optional($this->job)->assignedMatched;
        if ($assignedMatched) {
            return false;
        }

        if ($this->job_type == 'App\InterpreterJob' && !data_get($this->job, 'client.always_requires_a_quote', false)) {
            return true;
        }

        if ($this->job_type == 'App\TranslatorJob' && !data_get($this->job, 'client.always_requires_a_quote', false)) {
            return true;
        }

        $hasAcceptedAdminQuote = $this->job->adminQuotes()->where('status', 1)->exists();
        if ($hasAcceptedAdminQuote) {
            return true;
        }

        return false;
    }

    public function canBeCancelled()
    {
        return in_array($this->status, [
            'assigned',
            'accepted',
        ]);
    }

}
