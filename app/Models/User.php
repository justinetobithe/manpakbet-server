<?php

namespace App\Models;

use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_verified_at',
        'password',
        'balance',
        'is_active',
        'last_login_at',
        'provider',
        'provider_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'provider_id',
        'roles',
        'permissions',
    ];

    protected $appends = ['name', 'role'];

    protected $attributes = [
        'balance' => 0,
        'is_active' => true,
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isBackoffice(): bool
    {
        return $this->hasAnyRole(AccessControl::BACKOFFICE_ROLES) || $this->getAllPermissions()->isNotEmpty();
    }

    public function getNameAttribute(): string
    {
        $full = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $full !== '' ? $full : ($this->email ?? $this->phone ?? 'Player');
    }

    public function getRoleAttribute(): string
    {
        $names = $this->roles->pluck('name');

        foreach (AccessControl::PROTECTED_ROLES as $role) {
            if ($names->contains($role)) {
                return $role;
            }
        }

        return $names->first() ?? AccessControl::ROLE_USER;
    }

    public function toAuthArray(): array
    {
        return array_merge($this->toArray(), [
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
        ]);
    }
}
