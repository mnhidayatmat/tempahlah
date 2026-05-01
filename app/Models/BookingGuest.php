<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'is_lead', 'full_name', 'email', 'phone',
        'id_type', 'id_number_encrypted', 'country', 'is_foreigner',
    ];

    protected $casts = [
        'is_lead' => 'boolean',
        'is_foreigner' => 'boolean',
        'id_number_encrypted' => 'encrypted',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
