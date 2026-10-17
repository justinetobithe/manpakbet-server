<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycSubmission extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ID_TYPES = [
        'philsys' => 'PhilSys National ID',
        'passport' => 'Passport',
        'drivers_license' => "Driver's License",
        'umid' => 'UMID',
        'sss' => 'SSS ID',
        'prc' => 'PRC ID',
        'postal' => 'Postal ID',
        'voters' => "Voter's ID",
    ];

    public const FILE_FIELDS = ['id_front' => 'id_front_path', 'id_back' => 'id_back_path', 'selfie' => 'selfie_path'];

    protected $fillable = [
        'user_id',
        'status',
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'nationality',
        'occupation',
        'source_of_funds',
        'address',
        'city',
        'province',
        'zip_code',
        'id_type',
        'id_number',
        'id_front_path',
        'id_back_path',
        'selfie_path',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $hidden = ['id_front_path', 'id_back_path', 'selfie_path'];

    protected $appends = ['id_type_label', 'files', 'masked_id_number'];

    protected $casts = [
        'birthdate' => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getIdTypeLabelAttribute(): string
    {
        return self::ID_TYPES[$this->id_type] ?? $this->id_type;
    }

    public function getMaskedIdNumberAttribute(): string
    {
        $value = (string) $this->id_number;
        return strlen($value) > 4 ? str_repeat('•', strlen($value) - 4) . substr($value, -4) : $value;
    }

    public function getFilesAttribute(): array
    {
        return collect(self::FILE_FIELDS)
            ->filter(fn ($column) => !empty($this->{$column}))
            ->keys()
            ->values()
            ->all();
    }
}
