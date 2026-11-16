<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobSnapshot extends Model
{
    protected $fillable = [
        'job_id',
        'timesheet_id',
        'version',
        'hash',
        'source',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function job()
    {
        return $this->belongsTo(InterpreterJob::class, 'job_id');
    }

    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class, 'timesheet_id');
    }
}