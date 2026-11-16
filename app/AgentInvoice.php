<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentInvoice extends Model
{
    // Allow mass assignment for schema-defined fields used by invoice generation
    protected $guarded = [];
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'invoice_number',
        'invoice_date',
        'date_from',
        'date_to',
        'subtotal',
        'vat_amount',
        'total_amount',
        'status',
        'notes',
        'submitted_at',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function items()
    {
        return $this->hasMany(AgentInvoiceItem::class);
    }

    /**
     * Accessor that returns a human-friendly status label regardless of
     * whether the DB stores numeric codes or strings.
     */
    public function getStatusLabelAttribute(): string
    {
        $value = $this->attributes['status'] ?? null;
        if ($value === null) {
            return 'draft';
        }
        if (is_numeric($value)) {
            $map = [
                0 => 'draft',
                1 => 'submitted',
                2 => 'sent',
                3 => 'paid',
                4 => 'cancelled',
            ];
            $key = (int) $value;
            return $map[$key] ?? (string) $value;
        }
        return (string) $value;
    }
}
