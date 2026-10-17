<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAccount extends Model
{
    public const MAX_PER_USER = 10;

    protected $fillable = [
        'user_id',
        'method',
        'account_name',
        'account_number',
        'is_default',
    ];

    protected $hidden = [
        'account_number',
    ];

    protected $appends = [
        'masked_account_number',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        $head = $length > 7 ? 4 : 0;
        $tail = $length > 7 ? 3 : 2;

        return substr($value, 0, $head) . str_repeat('*', $length - $head - $tail) . substr($value, -$tail);
    }

    public static function normalizeNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0' . substr($digits, 2);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '0' . $digits;
        }

        return $digits;
    }

    public function getMaskedAccountNumberAttribute(): ?string
    {
        return self::mask($this->account_number);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
