<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'payslip_number',
        'agent_id',
        'agent_invoice_id',
        'period_start',
        'period_end',
        'items',
        'subtotal',
        'adjustments_total',
        'total',
        'status',
        'bank_snapshot',
        'approved_by',
        'approved_at',
        'paid_at',
        'payment_reference',
        'payment_note',
        'locked_by',
        'locked_at',
        'current_revision',
        'pdf_path',
    ];

    protected $casts = [
        'items' => 'array',
        'bank_snapshot' => 'array',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'locked_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function invoice()
    {
        return $this->belongsTo(AgentInvoice::class, 'agent_invoice_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
