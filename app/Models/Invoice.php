<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUlidPublicId;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';

    protected $fillable = [
        'tenant_id', 'public_id', 'booking_id', 'payment_id', 'template_id',
        'document_type', 'invoice_number', 'locale',
        'billed_to', 'line_items',
        'subtotal', 'sst_amount', 'tourism_tax_amount',
        'discount_amount', 'total', 'currency',
        'status', 'pdf_path',
        'issued_on', 'due_on', 'sent_at', 'viewed_at',
    ];

    protected $casts = [
        'billed_to' => 'array',
        'line_items' => 'array',
        'issued_on' => 'date',
        'due_on' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'sst_amount' => 'decimal:2',
        'tourism_tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'template_id');
    }
}
