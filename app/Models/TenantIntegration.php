<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantIntegration extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const PROVIDER_TOYYIBPAY = 'toyyibpay';
    public const PROVIDER_GCAL = 'google_calendar';
    public const PROVIDER_WHATSAPP = 'whatsapp';
    public const PROVIDER_SES = 'ses';
    public const PROVIDER_BILLPLZ = 'billplz';

    protected $fillable = [
        'tenant_id', 'provider', 'enabled', 'config',
        'connected_at', 'last_used_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'encrypted:array',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
}
