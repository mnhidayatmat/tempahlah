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
        'bank_account_holder', 'bank_account_number', 'bank_qr_path', 'status', 'sst_registered', 'sst_rate',
        'logo_path', 'invoice_tagline', 'business_address', 'invoice_terms',
        'primary_color', 'secondary_color', 'accent_color',
        'default_locale', 'suspended_at', 'suspended_reason',
        'full_payment_days_before', 'fee_payment_hours', 'cancel_balance_on',
        'auto_cancel_unpaid_balance', 'deposit_is_security', 'refund_policy',
        'checkout_reminder_enabled', 'checkout_reminder_hours', 'checkout_reminder_message',
        'auto_housekeeping',
        'manual_payment_instructions',
        'booking_link_shared_at',
    ];

    public const THEME_DEFAULTS = [
        'primary'   => '#2596c6',
        'secondary' => '#2cb8c4',
        'accent'    => '#e8b94a',
    ];

    /** Platform fallbacks for the booking payment lifecycle. */
    public const PAYMENT_POLICY_DEFAULTS = [
        'full_payment_days_before' => 7,
        'fee_payment_hours'        => 24,
        'cancel_balance_on'        => 'check_in', // 'due_date' | 'check_in'
        // OFF by default — collecting the balance on arrival is the common
        // homestay model, so a deposit-paid booking is never auto-cancelled
        // for an unpaid balance unless the host explicitly opts in.
        'auto_cancel_unpaid_balance' => false,
        // ON by default — the deposit is a separate refundable security
        // deposit: the guest pays the FULL total before check-in, and the host
        // refunds the deposit after check-out (matches the default invoice
        // terms). Set to false to credit the deposit toward the total and have
        // the balance reminder chase only (total − deposit).
        'deposit_is_security' => true,
    ];

    public const CANCEL_BALANCE_DUE_DATE = 'due_date';
    public const CANCEL_BALANCE_CHECK_IN = 'check_in';

    /**
     * Platform-wide default refund rule applied to every booking. Tenants
     * may append extra terms via the `refund_policy` column — the booking
     * fee being non-refundable is the non-negotiable baseline.
     */
    public const DEFAULT_REFUND_POLICY = 'The booking fee is non-refundable if you cancel your booking.';

    /**
     * Default terms printed on invoices/receipts when the tenant hasn't set
     * their own. Editable in Settings → Invoice & documents.
     */
    public const DEFAULT_INVOICE_TERMS = "Full payment must be made before check-in. The deposit is refunded after a satisfactory check-out.";

    protected $casts = [
        'motac_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'sst_registered' => 'boolean',
        'sst_rate' => 'decimal:4',
        'bank_account_encrypted' => 'encrypted',
        'full_payment_days_before' => 'integer',
        'fee_payment_hours' => 'integer',
        'auto_cancel_unpaid_balance' => 'boolean',
        'deposit_is_security' => 'boolean',
        'checkout_reminder_enabled' => 'boolean',
        'checkout_reminder_hours' => 'integer',
        'auto_housekeeping' => 'boolean',
        'booking_link_shared_at' => 'datetime',
    ];

    /** Platform default for the auto-housekeeping SOP master toggle. */
    public const AUTO_HOUSEKEEPING_DEFAULT = true;

    /** Platform defaults for the pre-checkout reminder. */
    public const CHECKOUT_REMINDER_DEFAULTS = [
        'enabled' => true,
        'hours'   => 3,
    ];

    /**
     * Default checkout guidelines used when the tenant hasn't written their
     * own. Kept short + friendly; the host can fully override it in Settings.
     */
    public const DEFAULT_CHECKOUT_MESSAGE = "A few things before you check out:\n• Please wash + stack any dishes used\n• Bag up the rubbish and leave it by the door\n• Switch off the aircond, fans and lights\n• Lock all doors and windows\n• Leave the keys where you found them";

    /** Balance reminder/due lead time (days before check-in). */
    public function fullPaymentDaysBefore(): int
    {
        return $this->full_payment_days_before !== null
            ? (int) $this->full_payment_days_before
            : self::PAYMENT_POLICY_DEFAULTS['full_payment_days_before'];
    }

    /** Hours a guest has to pay the booking fee before the booking auto-cancels. */
    public function feePaymentHours(): int
    {
        return $this->fee_payment_hours !== null
            ? (int) $this->fee_payment_hours
            : self::PAYMENT_POLICY_DEFAULTS['fee_payment_hours'];
    }

    /** When an unpaid balance auto-cancels: on the due date, or on check-in day. */
    public function cancelBalanceOn(): string
    {
        return in_array($this->cancel_balance_on, [self::CANCEL_BALANCE_DUE_DATE, self::CANCEL_BALANCE_CHECK_IN], true)
            ? $this->cancel_balance_on
            : self::PAYMENT_POLICY_DEFAULTS['cancel_balance_on'];
    }

    /**
     * Whether to auto-cancel a confirmed (deposit-paid) booking whose balance
     * is still unpaid past the deadline. OFF by default — most homestay hosts
     * collect the balance on arrival, so cancelling a paid reservation out
     * from under them is destructive. Opt-in for strict-prepayment hosts.
     */
    public function autoCancelUnpaidBalance(): bool
    {
        return $this->auto_cancel_unpaid_balance !== null
            ? (bool) $this->auto_cancel_unpaid_balance
            : self::PAYMENT_POLICY_DEFAULTS['auto_cancel_unpaid_balance'];
    }

    /**
     * Whether the deposit / booking fee is treated as a separate refundable
     * security deposit rather than a part-payment of the total.
     *
     * ON  → the balance reminder asks the guest to pay the FULL stay total
     *       (the deposit is NOT credited); the host refunds the deposit after
     *       a satisfactory check-out. This matches the platform's default
     *       invoice terms ("Full payment must be made before check-in. The
     *       deposit is refunded after a satisfactory check-out.").
     * OFF → legacy behaviour: the deposit is credited and the reminder chases
     *       the remaining balance (total − deposit).
     */
    public function depositIsSecurity(): bool
    {
        return $this->deposit_is_security !== null
            ? (bool) $this->deposit_is_security
            : self::PAYMENT_POLICY_DEFAULTS['deposit_is_security'];
    }

    /**
     * Whether housekeeping (cleaning + laundry) is auto-scheduled from bookings.
     * ON by default — a confirmed booking auto-creates a post-checkout turnover
     * (crew size + duration by turnaround) and a pre-arrival dusting when the
     * house has sat idle. Host can switch it off in Settings to schedule by hand.
     */
    public function autoHousekeepingEnabled(): bool
    {
        return $this->auto_housekeeping !== null
            ? (bool) $this->auto_housekeeping
            : self::AUTO_HOUSEKEEPING_DEFAULT;
    }

    /** Whether the pre-checkout WhatsApp reminder is on for this tenant. */
    public function checkoutReminderEnabled(): bool
    {
        return $this->checkout_reminder_enabled !== null
            ? (bool) $this->checkout_reminder_enabled
            : self::CHECKOUT_REMINDER_DEFAULTS['enabled'];
    }

    /** How many hours before checkout the reminder fires. */
    public function checkoutReminderHours(): int
    {
        $hours = $this->checkout_reminder_hours !== null
            ? (int) $this->checkout_reminder_hours
            : self::CHECKOUT_REMINDER_DEFAULTS['hours'];

        return max(1, $hours);
    }

    /** The checkout guidelines body — the tenant's own text, or the default. */
    public function checkoutReminderMessage(): string
    {
        $custom = trim((string) ($this->checkout_reminder_message ?? ''));

        return $custom !== '' ? $custom : __(self::DEFAULT_CHECKOUT_MESSAGE);
    }

    /**
     * Full refund/return policy shown to guests at booking time and on the
     * invoice: the platform default first, then any tenant-appended terms.
     */
    public function refundPolicyText(): string
    {
        $extra = trim((string) ($this->refund_policy ?? ''));

        return $extra !== ''
            ? __(self::DEFAULT_REFUND_POLICY)."\n".$extra
            : __(self::DEFAULT_REFUND_POLICY);
    }

    /**
     * Terms printed on the invoice / receipt. Falls back to a sensible default
     * so a brand-new tenant's documents still read professionally.
     */
    public function invoiceTermsText(): string
    {
        $terms = trim((string) ($this->invoice_terms ?? ''));

        return $terms !== '' ? $terms : __(self::DEFAULT_INVOICE_TERMS);
    }

    /** True when the tenant has enough bank detail to print a payment block. */
    public function hasBankDetails(): bool
    {
        return filled($this->bank_name)
            || filled($this->bank_account_number)
            || filled($this->bank_qr_path);
    }

    /**
     * Guest-facing instructions for a manual payment (bank name + account
     * number, DuitNow QR note, etc.). Null when the host hasn't set any —
     * callers should fall back to "contact the host to arrange payment".
     */
    public function manualPaymentInstructions(): ?string
    {
        $text = trim((string) ($this->manual_payment_instructions ?? ''));

        return $text !== '' ? $text : null;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function whatsappSession(): HasOne
    {
        return $this->hasOne(WhatsappSession::class);
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

    /**
     * The plan whose features this tenant currently holds — free|pro|ultra.
     * Comped / trialing / in-grace resolution lives in Subscription.
     */
    public function planKey(): string
    {
        return $this->subscription?->effectivePlanKey() ?? Subscription::PLAN_FREE;
    }

    public function planConfig(): array
    {
        return \App\Support\Billing\Plans::config($this->planKey());
    }

    /**
     * Does the current plan include a feature key (additive inheritance —
     * ultra holds everything pro holds)? Pennant flags resolve through here.
     */
    public function hasFeature(string $feature): bool
    {
        return \App\Support\Billing\Plans::hasFeature($this->planKey(), $feature);
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
        if (! $this->isPaid()) {
            return self::THEME_DEFAULTS['primary'];
        }

        return $this->normalizeHex($this->primary_color) ?? self::THEME_DEFAULTS['primary'];
    }

    public function themeSecondary(): string
    {
        if (! $this->isPaid()) {
            return self::THEME_DEFAULTS['secondary'];
        }

        return $this->normalizeHex($this->secondary_color) ?? self::THEME_DEFAULTS['secondary'];
    }

    public function themeAccent(): string
    {
        if (! $this->isPaid()) {
            return self::THEME_DEFAULTS['accent'];
        }

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
            '--logo-filter' => $this->themeLogoFilter(),
        ];

        return collect($vars)
            ->map(fn ($value, $key) => "{$key}: {$value};")
            ->implode(' ');
    }

    /**
     * CSS `filter` value that recolors the Tempahlah logo SVG to follow the
     * tenant's primary brand color. The source SVG's dominant hue is ~185°
     * (teal-cyan); we rotate to the tenant's primary hue and scale saturation
     * so highly-desaturated palettes (e.g. Modern Charcoal) render as grayscale.
     */
    public function themeLogoFilter(): string
    {
        [$h, $s] = $this->hexToHsl($this->themePrimary());

        // Source SVG dominant brand hue + saturation. Calibrated from the
        // teal-cyan palette baked into public/icons/logo.svg.
        $sourceHue = 185;
        $sourceSat = 70;

        $rotate = (int) round($h - $sourceHue);
        // Normalize to (-180, 180] so the shortest rotation is taken.
        $rotate = (($rotate + 180) % 360 + 360) % 360 - 180;

        // Near-grayscale primaries → strip saturation entirely.
        if ($s < 12) {
            return 'saturate(0)';
        }

        // Scale saturation proportionally, clamped so we don't blow out highlights.
        $saturate = round(min(1.6, max(0.35, $s / $sourceSat)), 2);

        return "hue-rotate({$rotate}deg) saturate({$saturate})";
    }

    /**
     * Convert a #rrggbb hex string to HSL with H in 0-360, S/L in 0-100.
     * Used by themeLogoFilter() to compute hue rotation.
     *
     * @return array{0:float,1:float,2:float} [hue, saturation, lightness]
     */
    protected function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return [0.0, 0.0, 50.0];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        $l = ($max + $min) / 2;
        $s = $delta === 0.0 ? 0.0 : $delta / (1 - abs(2 * $l - 1));

        $h = 0.0;
        if ($delta !== 0.0) {
            $h = match (true) {
                $max === $r => fmod((($g - $b) / $delta), 6),
                $max === $g => (($b - $r) / $delta) + 2,
                default     => (($r - $g) / $delta) + 4,
            };
            $h *= 60;
            if ($h < 0) {
                $h += 360;
            }
        }

        return [$h, $s * 100, $l * 100];
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
        $domain = config('app.tenant_domain');

        // Pro/Ultra perk: a clean subdomain (slug.tempahlah.com). Free tenants
        // publish under the apex path (tempahlah.com/slug) — and their subdomain
        // 404s, enforced in ResolveTenantFromSubdomain.
        if ($this->hasFeature('subdomain_booking_page')) {
            return $scheme.'://'.$this->slug.'.'.$domain.$portSuffix;
        }

        return $scheme.'://'.$domain.$portSuffix.'/'.$this->slug;
    }

    /** Has the host shared their booking link from the setup checklist? */
    public function bookingLinkShared(): bool
    {
        return $this->booking_link_shared_at !== null;
    }

    /** Stamp the moment the host shared their booking link. Idempotent. */
    public function markBookingLinkShared(): void
    {
        if ($this->booking_link_shared_at === null) {
            $this->forceFill(['booking_link_shared_at' => now()])->save();
        }
    }
}
