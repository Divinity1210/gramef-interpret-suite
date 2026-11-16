<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_invoice_id',
        'job_id',
        'agent_id',
        'job_type',
        'description',
        'amount',
        'surcharge',
        'other_charges',
        'vat_applicable',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'surcharge' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'vat_applicable' => 'boolean',
        'metadata' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
