<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\PaymentProof;

class Receipt extends Model
{
    protected $fillable = [
        'client_invoice_id',
        'receipt_number',
        'total_paid',
        'issued_at',
        'pdf_path',
        'generated_by',
        'payment_proof_ids',
        'metadata',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'payment_proof_ids' => 'array',
        'metadata' => 'array',
        'total_paid' => 'decimal:2',
    ];

    public function clientInvoice()
    {
        return $this->belongsTo(ClientInvoice::class, 'client_invoice_id');
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Accessor: Resolve payment_proof_ids to PaymentProof models.
     * Allows `$receipt->paymentProofs` to be used in views safely.
     */
    public function getPaymentProofsAttribute(): Collection
    {
        $ids = $this->payment_proof_ids ?? [];
        if (!is_array($ids) || empty($ids)) {
            return collect();
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return collect();
        }

        return PaymentProof::with('documents')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(function ($proof) use ($ids) {
                return array_search((int) $proof->id, $ids, true);
            })
            ->values();
    }
}