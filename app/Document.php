<?php
#

namespace App;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'job_id',
        'job_type',
        'url',
        'name',
        'type',
        'documentable_type',
        'documentable_id',
    ];

    protected $appends = ['fullUrl'];

    public function documentable()
    {
        return $this->morphTo();
    }

    public function getFullUrlAttribute()
    {
        $urlAttr = $this->url;
        if (empty($urlAttr)) {
            return '#';
        }
        $isTranslatorDoc = $this->documentable_type === 'App\\TranslatorJob' || strpos($urlAttr, 'translator-jobs/') !== false;
        $isInterpreterDoc = $this->documentable_type === 'App\\InterpreterJob' || strpos($urlAttr, 'interpreter-jobs/') !== false;
        $isPaymentProofDoc = $this->documentable_type === 'App\\PaymentProof' || strpos($urlAttr, 'payment-proofs/') !== false;

        if ($isTranslatorDoc) {
            $disk = env('TRANSLATOR_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isInterpreterDoc) {
            $disk = env('INTERPRETER_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isPaymentProofDoc) {
            $disk = env('PAYMENT_PROOFS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } else {
            $disk = 'public';
        }

        $relativePath = ltrim($urlAttr, '/');
        if (strpos($relativePath, 'storage/') === 0) { $relativePath = substr($relativePath, 8); }
        if (strpos($relativePath, 'public/') === 0) { $relativePath = substr($relativePath, 7); }

        if ($disk === 'public') {
            return route('documents.show', $this->id);
        }

        if ($disk === 's3') {
            return route('documents.show', $this->id);
        }

        // Fallback for other disks: best-effort public path
        return url('/storage/' . $relativePath);
    }
}
