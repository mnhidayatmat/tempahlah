<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'payment_id', 'provider', 'event_type',
        'external_id', 'signature_status', 'payload',
        'flagged', 'flag_reason', 'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'flagged' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
