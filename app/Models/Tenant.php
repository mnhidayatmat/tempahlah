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
        'logo_path', 'primary_color', 'secondary_color', 'accent_color',
        'default_locale', 'suspended_at', 'suspended_reason',
    ];

    public const THEME_DEFAULTS = [
        'primary'   => '#2596c6',
        'secondary' => '#2cb8c4',
        'accent'    => '#e8b94a',
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

    public function themePrimary(): string
    {
        return $this->normalizeHex($this->primary_color) ?? self::THEME_DEFAULTS['primary'];
    }

    public function themeSecondary(): string
    {
        return $this->normalizeHex($this->secondary_color) ?? self::THEME_DEFAULTS['secondary'];
    }

    public function themeAccent(): string
    {
        return $this->normalizeHex($this->accent_color) ?? self::THEME_DEFAULTS['accent'];
    }

    /**
     * CSS variable declarations that, when injected into <style>:root{...}</style>,
     * override the platform palette with the tenant's brand colors. Hover/deep/tint
     * variants are derived from primary via color-mix so a single hex picks a whole
     * coherent palette without the tenant having to tune shades by hand.
     */
    public function themeCssVariables(): string
    {
        $primary = $this->themePrimary();
        $secondary = $this->themeSecondary();
        $accent = $this->themeAccent();

        $vars = [
            '--primary' => $primary,
            '--primary-ink' => $this->contrastInk($primary),
            '--primary-hover' => "color-mix(in srgb, {$primary} 88%, #000)",
            '--primary-deep' => "color-mix(in srgb, {$primary} 75%, #000)",
            '--primary-tint' => "color-mix(in srgb, {$primary} 12%, #fff)",
            '--primary-soft' => "color-mix(in srgb, {$primary} 6%, #fff)",
            '--primary-edge' => "color-mix(in srgb, {$primary} 30%, #fff)",
            '--secondary' => $secondary,
            '--secondary-ink' => $this->contrastInk($secondary),
            '--secondary-tint' => "color-mix(in srgb, {$secondary} 10%, #fff)",
            '--accent' => $accent,
            '--accent-ink' => $this->contrastInk($accent),
            '--accent-tint' => "color-mix(in srgb, {$accent} 12%, #fff)",
        ];

        return collect($vars)
            ->map(fn ($value, $key) => "{$key}: {$value};")
            ->implode(' ');
    }

    /**
     * Pick black or white text based on the perceived luminance of the background
     * (YIQ formula). Keeps tenant CTAs readable even when they pick a pale yellow
     * or near-white primary.
     */
    protected function contrastInk(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = ($r * 299 + $g * 587 + $b * 114) / 1000;

        return $yiq >= 165 ? '#1a1614' : '#ffffff';
    }

    protected function normalizeHex(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $value = trim($value);
        if (! preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        return '#'.strtolower(ltrim($value, '#'));
    }

    public function publicUrl(): string
    {
        $appUrl = config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($appUrl, PHP_URL_PORT);
        $portSuffix = $port && ! in_array((int) $port, [80, 443], true) ? ':'.$port : '';

        return $scheme.'://'.$this->slug.'.'.config('app.tenant_domain').$portSuffix;
    }
}
