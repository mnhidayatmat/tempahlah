<?php

namespace App\Services\Agent;

use App\Models\Property;
use App\Support\Tenancy\BelongsToTenantScope;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Builds a default set of training Q&A pairs from the tenant's LIVE data —
 * rates, check-in/out times, deposit / booking fee, payment methods,
 * cancellation policy, capacity, facilities, tax and contact.
 *
 * These seed the agent's FAQ so a host gets a useful knowledge base the
 * moment they've filled in their homestay details. Every pair is tagged
 * source='auto' so the owner can freely regenerate without losing any
 * question they've hand-written or refined (those become source='custom').
 *
 * A pair is only emitted when the underlying data actually exists — we never
 * fabricate a price, address or policy the tenant hasn't set.
 */
class TrainingQaGenerator
{
    /** Hard cap so the config blob + system prompt stay sane. */
    public const MAX_PAIRS = 40;

    /**
     * @return array<int, array{q:string, a:string, source:string}>
     */
    public function generate(Tenant $tenant): array
    {
        $properties = Property::query()
            ->withoutGlobalScope(BelongsToTenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('status', Property::STATUS_ACTIVE)
            ->with(['rooms:id,property_id,base_price,max_adults,max_children', 'amenities:id,key,label_en'])
            ->orderBy('id')
            ->limit(10)
            ->get();

        $pairs = [];

        foreach ([
            $this->ratesPair($properties),
            $this->capacityPair($properties),
            $this->checkTimesPair($properties),
            $this->depositPair($tenant, $properties),
            $this->paymentPair($tenant),
            $this->cancellationPair($tenant),
            $this->locationPair($tenant, $properties),
            $this->facilitiesPair($properties),
            $this->taxPair($tenant),
            $this->houseRulesPair($properties),
            $this->contactPair($tenant),
            $this->availabilityPair(),
        ] as $pair) {
            if ($pair !== null) {
                $pairs[] = $pair + ['source' => 'auto'];
            }
        }

        return array_slice($pairs, 0, self::MAX_PAIRS);
    }

    /** @param \Illuminate\Support\Collection<int,Property> $properties */
    private function ratesPair($properties): ?array
    {
        $lines = [];
        foreach ($properties as $p) {
            $rate = round($p->startingNightlyRate(), 0);
            if ($rate <= 0) continue;
            $lines[] = $properties->count() > 1
                ? "{$p->name}: from RM" . number_format($rate) . "/night"
                : "RM" . number_format($rate) . " per night";
        }
        if (empty($lines)) return null;

        return [
            'q' => 'How much is it per night? / Berapa harga semalam?',
            'a' => count($lines) === 1
                ? $lines[0] . '. Rates can vary by dates — I can give an exact quote once you tell me your check-in and check-out.'
                : implode('. ', $lines) . '. I can give an exact total once you pick dates.',
        ];
    }

    /** @param \Illuminate\Support\Collection<int,Property> $properties */
    private function capacityPair($properties): ?array
    {
        $lines = [];
        foreach ($properties as $p) {
            $sleeps = (int) $p->rooms->sum(fn ($r) => (int) $r->max_adults + (int) $r->max_children);
            if ($sleeps <= 0) continue;
            $lines[] = $properties->count() > 1
                ? "{$p->name}: up to {$sleeps} pax"
                : "up to {$sleeps} guests";
        }
        if (empty($lines)) return null;

        return [
            'q' => 'How many people can stay? / Muat berapa orang?',
            'a' => (count($lines) === 1 ? ucfirst($lines[0]) : implode('; ', $lines))
                . '. Tell me how many pax and I\'ll confirm the best fit.',
        ];
    }

    /** @param \Illuminate\Support\Collection<int,Property> $properties */
    private function checkTimesPair($properties): ?array
    {
        // Map each property to its "in|out" window so we can collapse the common
        // case where every property shares the same check-in/out times.
        $windows = [];
        foreach ($properties as $p) {
            $in  = $this->fmtTime($p->check_in_time);
            $out = $this->fmtTime($p->check_out_time);
            if (! $in && ! $out) continue;
            $windows[$p->name] = 'check-in ' . ($in ?: '—') . ', check-out ' . ($out ?: '—');
        }
        if (empty($windows)) return null;

        $distinct = array_unique(array_values($windows));
        $answer = count($distinct) === 1
            ? ucfirst($distinct[0]) . '.'
            : implode('. ', array_map(
                fn ($name, $w) => "{$name}: {$w}",
                array_keys($windows),
                array_values($windows),
            )) . '.';

        return [
            'q' => 'What time is check-in and check-out? / Pukul berapa check-in / check-out?',
            'a' => $answer,
        ];
    }

    /** @param \Illuminate\Support\Collection<int,Property> $properties */
    private function depositPair(Tenant $tenant, $properties): ?array
    {
        $fees = $properties
            ->map(fn ($p) => (float) ($p->booking_fee_amount ?? 0))
            ->filter(fn ($f) => $f > 0);
        if ($fees->isEmpty()) return null;

        $min = $fees->min();
        $max = $fees->max();
        $feeText = $min === $max
            ? 'RM' . number_format($min)
            : 'RM' . number_format($min) . '–RM' . number_format($max);

        $policy = $tenant->depositIsSecurity()
            ? ' You pay the full stay amount before check-in, and the booking fee is refunded after a smooth check-out (it acts as a refundable security deposit).'
            : ' The booking fee counts toward your total — the balance is settled before/at check-in.';

        return [
            'q' => 'Is there a deposit or booking fee? / Ada deposit atau booking fee?',
            'a' => "To confirm a booking there's a booking fee of {$feeText}." . $policy,
        ];
    }

    private function paymentPair(Tenant $tenant): ?array
    {
        $parts = [];
        if ($tenant->hasBankDetails()) {
            $bank = trim(collect([
                $tenant->bank_name,
                $tenant->bank_account_number,
                $tenant->bank_account_holder,
            ])->filter()->implode(' · '));
            $parts[] = 'bank transfer' . ($bank !== '' ? " ({$bank})" : '');
        }
        if (filled($tenant->manualPaymentInstructions())) {
            $parts[] = trim($tenant->manualPaymentInstructions());
        }
        // Gateway (Toyyibpay/Billplz/SecurePay) — a pay link is generated per booking.
        $parts[] = 'online payment (a secure pay link is sent when you book)';

        if (empty($parts)) return null;

        return [
            'q' => 'How do I pay? / Macam mana nak bayar?',
            'a' => 'You can pay by ' . implode('; or ', array_unique($parts)) . '.',
        ];
    }

    private function cancellationPair(Tenant $tenant): ?array
    {
        $text = trim($tenant->refundPolicyText());
        if ($text === '') return null;

        return [
            'q' => 'What is your cancellation / refund policy? / Boleh cancel dan refund?',
            'a' => $text,
        ];
    }

    /** @param \Illuminate\Support\Collection<int,Property> $properties */
    private function locationPair(Tenant $tenant, $properties): ?array
    {
        $area = null;
        if (filled($tenant->business_address)) {
            $area = trim($tenant->business_address);
        } else {
            $first = $properties->first();
            if ($first) {
                $area = trim(collect([$first->city, $first->state])->filter()->implode(', '));
            }
        }
        if (! $area) return null;

        return [
            'q' => 'Where are you located? / Lokasi di mana?',
            'a' => "We're in {$area}. I can share the exact map location once your booking is on the way.",
        ];
    }

    /** @param \Illuminate\Support\Collection<int,Property> $properties */
    private function facilitiesPair($properties): ?array
    {
        $labels = $properties
            ->flatMap(fn ($p) => $p->amenities->pluck('label_en'))
            ->filter()
            ->unique()
            ->values();
        if ($labels->isEmpty()) return null;

        return [
            'q' => 'What facilities / amenities do you have? / Ada kemudahan apa?',
            'a' => 'Facilities include: ' . $labels->implode(', ') . '.',
        ];
    }

    private function taxPair(Tenant $tenant): ?array
    {
        $bits = [];
        if ($tenant->sst_registered && (float) $tenant->sst_rate > 0) {
            $bits[] = 'SST of ' . rtrim(rtrim(number_format((float) $tenant->sst_rate, 2), '0'), '.') . '% applies on accommodation';
        }
        $bits[] = 'foreign guests pay a tourism tax of RM10 per night (Malaysians are exempt)';

        return [
            'q' => 'Are there any taxes or extra charges? / Ada cukai atau caj tambahan?',
            'a' => ucfirst(implode('; ', $bits)) . '. Any applicable tax is shown clearly in your quote before you pay.',
        ];
    }

    /** @param \Illuminate\Support\Collection<int,Property> $properties */
    private function houseRulesPair($properties): ?array
    {
        $rules = $properties
            ->map(fn ($p) => trim((string) $p->house_rules))
            ->filter()
            ->unique()
            ->values();
        if ($rules->isEmpty()) return null;

        return [
            'q' => 'What are the house rules? / Apa peraturan rumah?',
            'a' => $rules->implode(' '),
        ];
    }

    private function contactPair(Tenant $tenant): ?array
    {
        if (blank($tenant->business_phone)) return null;

        return [
            'q' => 'How can I speak to the owner / a human? / Macam nak cakap dengan tuan rumah?',
            'a' => "You're already on our WhatsApp — the owner can jump in anytime. You can also reach us at {$tenant->business_phone}.",
        ];
    }

    private function availabilityPair(): ?array
    {
        return [
            'q' => 'Is it available on my dates? / Ada kosong tarikh saya?',
            'a' => 'Just tell me your check-in and check-out dates and how many pax, and I\'ll check availability right away.',
        ];
    }

    private function fmtTime(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        try {
            return Carbon::createFromFormat('H:i', substr($raw, 0, 5))->format('g:i A');
        } catch (\Throwable) {
            return null;
        }
    }
}
