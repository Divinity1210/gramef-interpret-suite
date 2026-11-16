<?php

namespace App;

class Quote extends BaseQuote
{
    protected $fillable = [
        'interpreting_cost',
        'travel_time',
        'travel_cost',
        'mileage_miles',
        'mileage_cost',
        'cost_description',
        'cost',
        'status',
    ];

    public function matchedAgent()
    {
        return $this->belongsTo('App\MatchedAgent');
    }

    public function getTotalAmountAttribute()
    {
        $matched = $this->matchedAgent;
        $job = $matched ? $matched->job : null;
        $hours = ($job instanceof \App\InterpreterJob) ? $job->totalHours : 0;
        $interpreting = ((float) ($this->interpreting_cost ?? 0)) * $hours;
        $travel = ((float) ($this->travel_cost ?? 0)) * ((float) ($this->travel_time ?? 0));
        $mileage = ((float) ($this->mileage_cost ?? 0)) * ((float) ($this->mileage_miles ?? 0));
        $total = $interpreting + $travel + $mileage + ((float) ($this->cost ?? 0));
        return '£' . number_format($total, 2);
    }
}
