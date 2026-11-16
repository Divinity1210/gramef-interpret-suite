<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentProof extends Model
{
    protected $fillable = [
        'client_invoice_id',
        'client_id',
        'submitted_by',
        'amount',
        'currency',
        'paid_at',
        'method',
        'reference',
        'notes',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'metadata' => 'array',
        'amount' => 'decimal:2',
    ];

    public function clientInvoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}