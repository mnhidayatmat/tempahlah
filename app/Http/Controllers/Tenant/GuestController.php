<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GuestBlacklistEntry;
use App\Models\User;
use App\Services\WhatsApp\PhoneNumber;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $guests = $this->buildGuestList($request);

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $guests = $guests->filter(function ($g) use ($needle) {
                return str_contains(mb_strtolower($g->name), $needle)
                    || str_contains(mb_strtolower($g->phone), $needle);
            })->values();
        }

        // Badge guests who carry a verified platform-wide flag. One batched query
        // over the listed guests' user ids + phones (no N+1).
        $flagged = $this->flaggedMap($guests);

        return view('tenant.guests.index', [
            'guests' => $guests,
            'flagged' => $flagged,
            'q' => $q,
            'totalGuests' => $guests->count(),
            'returning' => $guests->where('stays', '>', 1)->count(),
            'totalSpend' => (float) $guests->sum('spend'),
            'outstanding' => (float) $guests->sum('outstanding'),
        ]);
    }

    /**
     * Guest profile — stay history with this homestay, any verified platform-wide
     * flags, this tenant's own filed reports, and the report form. Guarded: the
     * guest must have booked with this tenant (bookings are tenant-scoped).
     */
    public function show(Request $request, User $guest)
    {
        $bookings = Booking::query()
            ->where('guest_id', $guest->id)
            ->with('property:id,name')
            ->orderByDesc('check_in')
            ->get();

        abort_if($bookings->isEmpty(), 404);

        $tenant = app(TenantContext::class)->current();

        // Verified flags from anywhere on the platform (the cross-tenant alert).
        $verified = GuestBlacklistEntry::approvedFlagsFor($guest->id, $guest->phone, $guest->email);

        // This tenant's own reports (incl. pending / rejected) for transparency.
        $myReports = GuestBlacklistEntry::query()
            ->where('reported_by_tenant_id', $tenant->id)
            ->where('guest_user_id', $guest->id)
            ->latest()
            ->get();

        return view('tenant.guests.show', [
            'guest' => $guest,
            'bookings' => $bookings,
            'verified' => $verified,
            'myReports' => $myReports,
            'stays' => $bookings->count(),
            'nights' => (int) $bookings->sum('nights'),
            'spend' => (float) $bookings->sum('total_amount'),
            'reasons' => GuestBlacklistEntry::REASON_LABELS,
            'severities' => GuestBlacklistEntry::SEVERITY_LABELS,
        ]);
    }

    /**
     * File a blacklist report against a guest. Created PENDING — a platform admin
     * must verify it before it flags the guest for other homestays. Snapshots the
     * guest's identity (phone/email/name) so cross-tenant matching works even if
     * the guest later books under a different User row elsewhere.
     */
    public function report(Request $request, User $guest)
    {
        $tenant = app(TenantContext::class)->current();

        // Only a homestay that has actually hosted this guest may report them.
        $lastBooking = Booking::query()
            ->where('guest_id', $guest->id)
            ->orderByDesc('check_in')
            ->first();

        abort_if(! $lastBooking, 403);

        $data = $request->validate([
            'severity' => ['required', Rule::in(array_keys(GuestBlacklistEntry::SEVERITY_LABELS))],
            'reason_code' => ['required', Rule::in(array_keys(GuestBlacklistEntry::REASON_LABELS))],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'booking_id' => ['nullable', 'integer'],
        ]);

        // If a booking_id was passed, make sure it belongs to this tenant + guest.
        $bookingId = null;
        if (! empty($data['booking_id'])) {
            $bookingId = Booking::query()
                ->where('id', $data['booking_id'])
                ->where('guest_id', $guest->id)
                ->value('id');
        }

        GuestBlacklistEntry::create([
            'guest_user_id' => $guest->id,
            'reported_by_tenant_id' => $tenant->id,
            'booking_id' => $bookingId ?: $lastBooking->id,
            'guest_name' => $guest->name,
            'guest_phone' => PhoneNumber::normalize($guest->phone),
            'guest_email' => $guest->email ? mb_strtolower(trim($guest->email)) : null,
            'severity' => $data['severity'],
            'reason_code' => $data['reason_code'],
            'description' => trim($data['description']),
            'review_status' => GuestBlacklistEntry::STATUS_PENDING,
        ]);

        return redirect()
            ->route('tenant.guests.show', $guest->id)
            ->with('status', __('Report submitted. Our team will review it before it flags this guest for other homestays.'));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $guests = $this->buildGuestList($request);

        return response()->streamDownload(function () use ($guests) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Phone', 'Stays', 'Nights', 'Lifetime spend (RM)', 'Outstanding (RM)', 'Last stay', 'Last property', 'Channels']);
            foreach ($guests as $g) {
                fputcsv($out, [
                    $g->name,
                    $g->phone,
                    $g->stays,
                    $g->nights,
                    number_format($g->spend, 2, '.', ''),
                    number_format($g->outstanding, 2, '.', ''),
                    is_string($g->last_checkin) ? $g->last_checkin : optional($g->last_checkin)->format('Y-m-d'),
                    $g->last_property ?? '',
                    implode('|', $g->channels),
                ]);
            }
            fclose($out);
        }, 'guests-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildGuestList(Request $request): \Illuminate\Support\Collection
    {
        $bookings = Booking::query()
            ->with(['guest:id,name,phone,email', 'property:id,name'])
            ->whereNotNull('guest_id')
            ->whereIn('status', [
                Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN,
                Booking::STATUS_CHECKED_OUT, Booking::STATUS_PENDING,
            ])
            ->get();

        return $bookings->groupBy('guest_id')->map(function ($group) {
            $first = $group->first();
            $latest = $group->sortByDesc('check_in')->first();
            $totalPaid = $group->sum(function ($b) {
                $deposit = $b->deposit_paid_at ? (float) $b->deposit_amount : 0;
                $balance = $b->balance_paid_at ? max(0, (float) $b->total_amount - $deposit) : 0;
                return $deposit + $balance;
            });
            $totalSpend = (float) $group->sum('total_amount');
            return (object) [
                'id' => $first->guest_id,
                'name' => $first->guest?->name ?? '—',
                'phone' => $first->guest?->phone ?? '—',
                'email' => $first->guest?->email,
                'stays' => $group->count(),
                'nights' => (int) $group->sum('nights'),
                'spend' => $totalSpend,
                'outstanding' => max(0, $totalSpend - $totalPaid),
                'last_checkin' => $latest->check_in,
                'last_property' => $latest->property?->name,
                'channels' => $group->pluck('channel')->unique()->values()->all(),
            ];
        })->sortByDesc('spend')->values();
    }

    /**
     * Map of guest user_id => most-severe verified flag, for badging the list.
     * One query keyed on the listed guests' ids + normalized phones.
     *
     * @return array<int, \App\Models\GuestBlacklistEntry>
     */
    protected function flaggedMap(\Illuminate\Support\Collection $guests): array
    {
        $ids = $guests->pluck('id')->filter()->all();
        $phones = $guests->pluck('phone')
            ->map(fn ($p) => PhoneNumber::normalize($p))
            ->filter()->unique()->values()->all();

        if (! $ids && ! $phones) {
            return [];
        }

        $entries = GuestBlacklistEntry::query()
            ->approved()
            ->where(function ($w) use ($ids, $phones) {
                if ($ids) {
                    $w->orWhereIn('guest_user_id', $ids);
                }
                if ($phones) {
                    $w->orWhereIn('guest_phone', $phones);
                }
            })
            ->get();

        $rank = [
            GuestBlacklistEntry::SEVERITY_BLACKLIST => 3,
            GuestBlacklistEntry::SEVERITY_WARNING => 2,
            GuestBlacklistEntry::SEVERITY_NOTE => 1,
        ];

        $map = [];
        foreach ($guests as $g) {
            $normPhone = PhoneNumber::normalize($g->phone);
            $match = $entries
                ->filter(fn ($e) => $e->guest_user_id === $g->id || ($normPhone && $e->guest_phone === $normPhone))
                ->sortByDesc(fn ($e) => $rank[$e->severity] ?? 0)
                ->first();
            if ($match) {
                $map[$g->id] = $match;
            }
        }

        return $map;
    }
}
