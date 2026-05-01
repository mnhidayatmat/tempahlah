<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPublicId;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUlidPublicId;
    use Notifiable;
    use SoftDeletes;

    public const TYPE_TENANT_USER = 'tenant_user';
    public const TYPE_GUEST = 'guest';

    protected $fillable = [
        'public_id', 'name', 'email', 'phone', 'password',
        'email_verified_at', 'phone_verified_at', 'locale',
        'mykad_encrypted', 'avatar_path', 'fcm_tokens',
        'user_type', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password', 'remember_token', 'mykad_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'fcm_tokens' => 'array',
            'mykad_encrypted' => 'encrypted',
            'password' => 'hashed',
        ];
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot(['role', 'status', 'invited_at', 'joined_at']);
    }

    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_user_id');
    }

    public function activeTenants()
    {
        return $this->tenants()->wherePivot('status', 'active');
    }

    public function isGuest(): bool
    {
        return $this->user_type === self::TYPE_GUEST;
    }
}
