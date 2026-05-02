<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Services\Reports\StatisticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(private StatisticsService $stats) {}

    public function index()
    {
        return view('tenant.reports.index', $this->buildReportData());
    }

    public function exportPdf()
    {
        $data = $this->buildReportData();
        $data['tenant'] = app(\App\Support\Tenancy\TenantContext::class)->current();
        $data['generatedAt'] = now();

        $pdf = Pdf::loadView('tenant.reports.pdf', $data)->setPaper('a4', 'portrait');

        $filename = 'report-'.$data['periodStart']->format('Y-m').'-to-'.$data['periodEnd']->format('Y-m').'.pdf';
        return $pdf->download($filename);
    }

    protected function buildReportData(): array
    {
        $end = Carbon::now()->endOfMonth();
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $priorEnd = $start->copy()->subDay();
        $priorStart = $priorEnd->copy()->subMonths(11)->startOfMonth();

        $monthly = collect(range(0, 11))->map(function ($i) use ($start) {
            $monthStart = $start->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            return [
                'label' => $monthStart->format("M'y"),
                'revenue' => $this->stats->revenue($monthStart, $monthEnd),
                'occupancy' => $this->stats->occupancy($monthStart, $monthEnd) / 100,
            ];
        });

        $totalRevenue = (float) $monthly->sum('revenue');
        $priorRevenue = $this->stats->revenue($priorStart, $priorEnd);
        $revDelta = $priorRevenue > 0 ? ($totalRevenue - $priorRevenue) / $priorRevenue : null;

        $occupancyAvg = (float) $monthly->avg('occupancy');
        $priorOccupancy = $this->stats->occupancy($priorStart, $priorEnd) / 100;
        $occDelta = $priorOccupancy > 0 ? $occupancyAvg - $priorOccupancy : null;

        $adr = $this->stats->adr($start, $end);
        $priorAdr = $this->stats->adr($priorStart, $priorEnd);
        $adrDelta = $priorAdr > 0 ? ($adr - $priorAdr) / $priorAdr : null;

        $availableNights = max(1, $start->diffInDays($end));
        $revPAR = $availableNights > 0
            ? $totalRevenue / $availableNights / max(1, \App\Models\Room::where('status', 'active')->count())
            : 0;

        $properties = Property::query()
            ->with(['rooms:id,property_id,status'])
            ->get(['id', 'name', 'city', 'state'])
            ->map(function ($p) use ($start, $end) {
                $bookings = Booking::query()
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
                    ->where('property_id', $p->id)
                    ->whereBetween('check_in', [$start, $end])
                    ->get(['nights', 'total_amount']);
                $rev = (float) $bookings->sum('total_amount');
                $nights = (int) $bookings->sum('nights');
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'rev' => $rev,
                    'stays' => $bookings->count(),
                    'nights' => $nights,
                    'adr' => $nights > 0 ? round($rev / $nights) : 0,
                ];
            })
            ->sortByDesc('rev')
            ->values();

        $sourceBookings = Booking::query()
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_CHECKED_OUT])
            ->whereBetween('check_in', [$start, $end])
            ->get(['channel', 'total_amount']);
        $channels = $sourceBookings->groupBy('channel')->map->sum('total_amount');
        $channelTotal = max(1, $channels->sum());

        return [
            'periodStart' => $start,
            'periodEnd' => $end,
            'monthly' => $monthly,
            'totalRevenue' => $totalRevenue,
            'revDelta' => $revDelta,
            'occupancyAvg' => $occupancyAvg,
            'occDelta' => $occDelta,
            'adr' => $adr,
            'adrDelta' => $adrDelta,
            'revPAR' => $revPAR,
            'properties' => $properties,
            'channels' => $channels,
            'channelTotal' => (float) $channelTotal,
        ];
    }
}
