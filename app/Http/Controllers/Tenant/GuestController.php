<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
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

        return view('tenant.guests.index', [
            'guests' => $guests,
            'q' => $q,
            'totalGuests' => $guests->count(),
            'returning' => $guests->where('stays', '>', 1)->count(),
            'totalSpend' => (float) $guests->sum('spend'),
            'outstanding' => (float) $guests->sum('outstanding'),
        ]);
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
            ->with(['guest:id,name,phone', 'property:id,name'])
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
                'name' => $first->guest?->name ?? '—',
                'phone' => $first->guest?->phone ?? '—',
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
}
