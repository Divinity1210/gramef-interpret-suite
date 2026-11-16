<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentJobEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'job_id',
        'job_type',
        'base_amount',
        'expenses',
        'surcharge',
        'other_charges',
        'total_amount',
        'notes',
        'invoiced'
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'expenses' => 'decimal:2',
        'surcharge' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoiced' => 'boolean'
    ];

    /**
     * Get the agent that owns the job entry.
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the job associated with this entry (polymorphic).
     */
    public function job()
    {
        return $this->morphTo('job', 'job_type', 'job_id');
    }

    /**
     * Expense items breakdown for this job entry.
     */
    public function expensesItems()
    {
        return $this->hasMany(AgentJobEntryExpense::class, 'agent_job_entry_id');
    }

    /**
     * Calculate and update the total amount.
     */
    public function calculateTotal()
    {
        $this->total_amount = $this->base_amount + $this->expenses + $this->surcharge + $this->other_charges;
        return $this->total_amount;
    }

    /**
     * Scope to get entries that haven't been invoiced yet.
     */
    public function scopePending($query)
    {
        return $query->where('invoiced', false);
    }

    /**
     * Scope to get entries for a specific agent.
     */
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }
}
