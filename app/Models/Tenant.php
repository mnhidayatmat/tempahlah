<?php

namespace App\Models;

use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory;
    use HasUlidPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id', 'slug', 'business_name', 'business_email', 'business_phone',
        'ssm_number', 'motac_license', 'motac_verified_at', 'owner_user_id',
        'kyc_status', 'kyc_documents_path', 'bank_account_encrypted', 'bank_name',
        'bank_account_holder', 'status', 'sst_registered', 'sst_rate',
        'logo_path', 'primary_color', 'default_locale', 'suspended_at', 'suspended_reason',
    ];

    protected $casts = [
        'motac_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'sst_registered' => 'boolean',
        'sst_rate' => 'decimal:4',
        'bank_account_encrypted' => 'encrypted',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot(['role', 'status', 'invited_at', 'joined_at']);
    }

    public function isPaid(): bool
    {
        return $this->subscription?->isPaid() ?? false;
    }

    public function isOnTrial(): bool
    {
        return $this->subscription?->onTrial() ?? false;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->suspended_at === null;
    }
}
