<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AgentJobEntryExpense extends Model
{
    protected $table = 'agent_job_entry_expenses';

    protected $fillable = [
        'agent_job_entry_id',
        'type',
        'amount',
        'evidence_path',
    ];

    public function jobEntry()
    {
        return $this->belongsTo(AgentJobEntry::class, 'agent_job_entry_id');
    }

    /**
     * Get the full URL for the evidence file.
     */
    public function getEvidenceUrlAttribute(): ?string
    {
        if (!$this->evidence_path) {
            return null;
        }
        $disk = env('AGENT_DOCS_DISK') ?: 'public';
        if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($this->evidence_path)) {
            return asset('storage/' . $this->evidence_path);
        }
        return null;
    }
}