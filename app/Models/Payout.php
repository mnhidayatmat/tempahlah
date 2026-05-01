<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlidPublicId;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'public_id', 'tenant_id',
        'period_start', 'period_end',
        'booking_count',
        'gross_total', 'commission_total', 'gateway_fees_total', 'net_amount',
        'currency', 'status',
        'bank_reference', 'processed_at', 'statement_pdf_path',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross_total' => 'decimal:2',
        'commission_total' => 'decimal:2',
        'gateway_fees_total' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
