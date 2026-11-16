<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_invoice_id',
        'job_id',
        'job_type',
        'description',
        'base_amount',
        'expenses_amount',
        'surcharge',
        'other_charges',
        'vat_applicable',
        'total_amount',
        'metadata',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'expenses_amount' => 'decimal:2',
        'surcharge' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'vat_applicable' => 'boolean',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function agentInvoice()
    {
        return $this->belongsTo(AgentInvoice::class, 'agent_invoice_id');
    }

    public function invoice()
    {
        return $this->belongsTo(AgentInvoice::class, 'agent_invoice_id');
    }

    public function job()
    {
        return $this->morphTo();
    }

    public function payslip()
    {
        return $this->belongsTo(Payslip::class, 'payslip_id');
    }
}
