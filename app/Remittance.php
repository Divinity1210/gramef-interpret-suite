<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;

class Remittance extends Model
{
    use HasFactory;

    protected $fillable = [
        'remittance_number',
        'client_invoice_id',
        'client_id',
        'amount',
        'payment_date',
        'file_path',
        'original_filename',
        'slip_path',
        'status',
        'uploaded_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    // Backwards-compatible alias
    public function invoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    // Preferred relation name used in controllers
    public function clientInvoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
