<?php

namespace App\Http\Requests\Public;

use App\Models\Property;
use App\Models\Room;
use App\Services\WhatsApp\PhoneNumber;
use App\Support\Tenancy\BelongsToTenantScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for public-facing direct-booking submissions on a tenant
 * subdomain. The tenant is resolved by ResolveTenantFromSubdomain
 * middleware and stashed in `$request->attributes->subdomain_tenant`.
 *
 * Both property_id and room_id must belong to that same tenant — we
 * verify this server-side using a withoutGlobalScopes() query so we don't
 * trust the form input.
 */
class StoreBookingRequest extends FormRequest
{
    /**
     * Countries offered on the public booking form, mirroring the dashboard's
     * manual-booking form. `OT` = other. Anything that isn't `MY` is a foreign
     * guest and attracts the RM 10/night tourism tax.
     *
     * @var array<string, string>
     */
    public const COUNTRY_LABELS = [
        'MY' => '🇲🇾 Malaysia',
        'SG' => '🇸🇬 Singapore',
        'ID' => '🇮🇩 Indonesia',
        'TH' => '🇹🇭 Thailand',
        'CN' => '🇨🇳 China',
        'JP' => '🇯🇵 Japan',
        'AU' => '🇦🇺 Australia',
        'GB' => '🇬🇧 United Kingdom',
        'US' => '🇺🇸 United States',
        'OT' => '🌐 Other',
    ];

    public const COUNTRIES = ['MY', 'SG', 'ID', 'TH', 'CN', 'JP', 'AU', 'GB', 'US', 'OT'];

    /** "How did you hear about us?" — a marketing-insight field, optional. */
    public const REFERRAL_SOURCES = ['instagram', 'facebook', 'friend', 'google', 'repeat', 'other'];

    public function authorize(): bool
    {
        return $this->attributes->get('subdomain_tenant') !== null;
    }

    /** The referral source if it's one of the known options, else null. */
    public function referralSource(): ?string
    {
        $v = strtolower(trim((string) $this->input('referral_source', '')));

        return in_array($v, self::REFERRAL_SOURCES, true) ? $v : null;
    }

    /**
     * The guest's country, upper-cased, defaulting to Malaysian.
     */
    public function guestCountry(): string
    {
        $code = strtoupper((string) $this->input('guest_country', 'MY'));

        return in_array($code, self::COUNTRIES, true) ? $code : 'MY';
    }

    /**
     * Tourism tax (RM 10/night) applies to foreign guests only.
     */
    public function guestIsForeigner(): bool
    {
        return $this->guestCountry() !== 'MY';
    }

    public function rules(): array
    {
        return [
            'property_id'      => ['required', 'integer', 'exists:properties,id'],
            'room_id'          => ['required', 'integer', 'exists:rooms,id'],
            'check_in'         => ['required', 'date', 'after_or_equal:today'],
            'check_out'        => ['required', 'date', 'after:check_in'],
            'adults'           => ['required', 'integer', 'min:1', 'max:200'],
            'children'         => ['nullable', 'integer', 'min:0', 'max:200'],
            'guest_name'       => ['required', 'string', 'min:2', 'max:120'],
            'guest_email'      => ['required', 'email:rfc', 'max:160'],
            'guest_phone'      => ['required', 'string', 'min:7', 'max:24'],
            // Drives the RM 10/night tourism tax, which is levied on foreign
            // guests only. Absent (an older cached form) falls back to Malaysian,
            // matching the previous behaviour rather than over-charging.
            'guest_country'    => ['nullable', 'string', 'size:2', Rule::in(self::COUNTRIES)],
            'special_requests' => ['nullable', 'string', 'max:500'],
            // Optional "how did you hear about us?" — marketing insight only,
            // never affects pricing/commission. Unknown values are dropped.
            'referral_source'  => ['nullable', 'string', Rule::in(self::REFERRAL_SOURCES)],
            // How the guest chose to pay. Null / absent defaults to the
            // online gateway in the controller.
            'payment_method'   => ['nullable', Rule::in(['gateway', 'manual'])],
            // Host-set agreed price + its HMAC signature (from a "Send booking
            // form" link). Honoured only if the signature re-verifies in the
            // controller — otherwise ignored and the price is recomputed.
            'price'            => ['nullable', 'numeric', 'min:0', 'max:'.\App\Support\Booking\QuotedPrice::MAX_AMOUNT],
            'psig'             => ['nullable', 'string', 'max:128'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $tenant = $this->attributes->get('subdomain_tenant');
            if (! $tenant) return;

            $propertyId = (int) $this->input('property_id');
            $roomId     = (int) $this->input('room_id');

            $property = Property::query()
                ->withoutGlobalScope(BelongsToTenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('id', $propertyId)
                ->where('status', Property::STATUS_ACTIVE)
                ->first();

            if (! $property) {
                $v->errors()->add('property_id', __('Selected property is not available.'));
                return;
            }

            $room = Room::query()
                ->withoutGlobalScope(BelongsToTenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('property_id', $property->id)
                ->where('id', $roomId)
                ->first();

            if (! $room) {
                $v->errors()->add('room_id', __('Selected room is not available.'));
            }

            // Phone must normalize to E.164 (PhoneNumber returns null on
            // garbage). We do this in withValidator instead of a custom
            // rule so the normalized value is available to the controller
            // via $this->normalizedPhone().
            $normalized = PhoneNumber::normalize($this->input('guest_phone'));
            if (! $normalized) {
                $v->errors()->add('guest_phone', __('Please enter a valid phone number, e.g. 0123456789.'));
            }
        });
    }

    public function normalizedPhone(): ?string
    {
        return PhoneNumber::normalize($this->input('guest_phone'));
    }
}
