<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Remittance;

class ClientInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'invoice_number',
        'company_id',
        'date_range_start',
        'date_range_end',
        'template',
        'include_vat',
        'vat_rate',
        'subtotal',
        'vat_amount',
        'total',
        'status',
        'sent_at',
        'pdf_path',
        // New template-specific fields
        'invoice_date',
        'payment_terms_text',
        'file_reference',
        'client_reference',
        'patient_number',
        'pid',
        'interpreting_time_minutes',
        'travel_time_minutes',
        'parking_amount',
    ];

    protected $casts = [
        'date_range_start' => 'date',
        'date_range_end' => 'date',
        'include_vat' => 'boolean',
        'vat_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'sent_at' => 'datetime',
        // New casts
        'invoice_date' => 'date',
        'due_date' => 'date',
        'last_chased_at' => 'datetime',
        'chaser_paused' => 'boolean',
        'interpreting_time_minutes' => 'integer',
        'travel_time_minutes' => 'integer',
        'parking_amount' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(ClientInvoiceItem::class);
    }

    public function remittance()
    {
        return $this->hasOne(Remittance::class, 'client_invoice_id');
    }

    // New: multiple flexible payments provided by clients (payment proofs)
    public function paymentProofs()
    {
        return $this->hasMany(PaymentProof::class, 'client_invoice_id');
    }

    // New: receipts generated after verification
    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'client_invoice_id');
    }
}
