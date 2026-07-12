<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceTemplate extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'document_type', 'locale_default',
        'logo_path', 'color_primary', 'number_prefix', 'next_number',
        'header_html', 'footer_html', 'terms_text', 'payment_instructions',
        'show_sst', 'show_tourism_tax', 'is_default',
    ];

    protected $casts = [
        'show_sst' => 'boolean',
        'show_tourism_tax' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'template_id');
    }

    public function nextInvoiceNumber(): string
    {
        $number = $this->next_number;
        $this->increment('next_number');
        return sprintf('%s-%s-%04d', $this->number_prefix, now(config('homestay.timezone', 'Asia/Kuala_Lumpur'))->format('y'), $number);
    }
}
