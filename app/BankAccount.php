<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class BankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_holder',
        'account_number',
        'sort_code',
        'consent',
        'consent_at',
        'retain_until',
    ];

    protected $hidden = [
        'account_number',
        'sort_code',
    ];

    protected $casts = [
        'consent' => 'boolean',
        'consent_at' => 'datetime',
        'retain_until' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Encrypt sensitive attributes on set
    public function setAccountNumberAttribute($value)
    {
        $this->attributes['account_number'] = $value ? Crypt::encryptString(trim($value)) : Crypt::encryptString('');
    }

    public function setSortCodeAttribute($value)
    {
        $this->attributes['sort_code'] = $value ? Crypt::encryptString(trim($value)) : Crypt::encryptString('');
    }

    // Decrypted accessors (explicit, to avoid accidental exposure via toArray)
    public function getAccountNumberFullAttribute()
    {
        $encrypted = $this->attributes['account_number'] ?? null;
        if (!$encrypted) { return null; }
        try { return Crypt::decryptString($encrypted); } catch (\Throwable $e) { return null; }
    }

    public function getSortCodeFullAttribute()
    {
        $encrypted = $this->attributes['sort_code'] ?? null;
        if (!$encrypted) { return null; }
        try { return Crypt::decryptString($encrypted); } catch (\Throwable $e) { return null; }
    }

    public function getAccountNumberMaskedAttribute()
    {
        $raw = $this->account_number_full;
        return self::maskAccountNumber($raw);
    }

    public function getSortCodeMaskedAttribute()
    {
        $raw = $this->sort_code_full;
        return self::maskSortCode($raw);
    }

    public static function maskAccountNumber(?string $value): string
    {
        if (!$value) { return 'N/A'; }
        $digits = preg_replace('/\s+/', '', $value);
        $len = strlen($digits);
        if ($len <= 4) { return str_repeat('*', max($len-2, 0)) . substr($digits, -2); }
        return str_repeat('*', $len - 4) . substr($digits, -4);
    }

    public static function maskSortCode(?string $value): string
    {
        if (!$value) { return 'N/A'; }
        $digits = preg_replace('/[^0-9]/', '', $value);
        $len = strlen($digits);
        if ($len <= 2) { return str_repeat('*', max($len, 0)); }
        $masked = str_repeat('*', max($len - 2, 0)) . substr($digits, -2);
        // format as XX-XX-XX with masking preserved
        $chunks = str_split($masked, 2);
        return implode('-', $chunks);
    }
}