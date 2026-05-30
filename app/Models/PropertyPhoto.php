<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyPhoto extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'property_id', 'room_id',
        'path', 'disk', 'caption_bm', 'caption_en', 'category',
        'sort_order', 'is_hero',
    ];

    /**
     * Categories a tenant can tag a photo with so guests can browse
     * "show me the kitchen / bathroom / pool" cleanly. Each entry has
     * an emoji icon + BM/EN label.
     *
     * @return array<string, array{emoji:string, bm:string, en:string}>
     */
    public static function categories(): array
    {
        return [
            'exterior'      => ['emoji' => '🏡',  'bm' => 'Luaran',         'en' => 'Exterior'],
            'living'        => ['emoji' => '🛋️',  'bm' => 'Ruang tamu',     'en' => 'Living room'],
            'kitchen'       => ['emoji' => '🍳',  'bm' => 'Dapur',          'en' => 'Kitchen'],
            'dining'        => ['emoji' => '🍽️',  'bm' => 'Ruang makan',    'en' => 'Dining'],
            'bedroom'       => ['emoji' => '🛏️',  'bm' => 'Bilik tidur',    'en' => 'Bedroom'],
            'bathroom'      => ['emoji' => '🚿',  'bm' => 'Bilik air',      'en' => 'Bathroom'],
            'pool'          => ['emoji' => '🏊',  'bm' => 'Kolam',          'en' => 'Pool'],
            'outdoor'       => ['emoji' => '🌿',  'bm' => 'Luar / taman',   'en' => 'Outdoor & garden'],
            'view'          => ['emoji' => '🌄',  'bm' => 'Pemandangan',    'en' => 'View'],
            'entertainment' => ['emoji' => '🎮',  'bm' => 'Hiburan',        'en' => 'Entertainment'],
            'surau'         => ['emoji' => '🤲',  'bm' => 'Surau',          'en' => 'Surau / prayer'],
            'other'         => ['emoji' => '📷',  'bm' => 'Lain-lain',      'en' => 'Other'],
        ];
    }

    protected $casts = [
        'is_hero' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function url(): string
    {
        return \Storage::disk($this->disk)->url($this->path);
    }
}
