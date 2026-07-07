<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Operations\GenerateOperationalTasksForBooking;
use App\Http\Controllers\Controller;
use App\Models\Cleaner;
use App\Models\CleaningTask;
use App\Models\LaundryTask;
use App\Models\LaundryVendor;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HousekeepingController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'cleaning');
        $today = Carbon::today();
        // Show the auto-scheduled turnover schedule well ahead (not just 7 days)
        // so the host can see + share the whole upcoming cleaning plan.
        $weekEnd = $today->copy()->addDays(60);

        // Date the copy-paste WhatsApp schedule is built for (defaults today;
        // host can pick tomorrow the night before to brief the cleaner group).
        try {
            $scheduleDate = $request->filled('schedule_date')
                ? Carbon::parse($request->query('schedule_date'))->startOfDay()
                : $today->copy();
        } catch (\Throwable) {
            $scheduleDate = $today->copy();
        }

        $todayTasks = CleaningTask::query()
            ->with(['property:id,name', 'room:id,name', 'assignee:id,name', 'cleaner:id,name,phone', 'booking:id,reference,guest_id', 'booking.guest:id,name'])
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $upcoming = CleaningTask::query()
            ->with(['property:id,name', 'room:id,name', 'assignee:id,name', 'cleaner:id,name,phone', 'booking:id,reference,guest_id', 'booking.guest:id,name'])
            ->whereDate('scheduled_at', '>', $today)
            ->whereDate('scheduled_at', '<=', $weekEnd)
            ->orderBy('scheduled_at')
            ->get();

        $issues = CleaningTask::query()
            ->whereNotNull('issues')
            ->where('status', '!=', CleaningTask::STATUS_COMPLETED)
            ->count();

        $cleaningStats = [
            'today' => $todayTasks->count(),
            'in_progress' => $todayTasks->where('status', CleaningTask::STATUS_IN_PROGRESS)->count(),
            'completed' => $todayTasks->where('status', CleaningTask::STATUS_COMPLETED)->count(),
            'issues' => $issues,
        ];

        $laundry = LaundryTask::query()
            ->with(['property:id,name', 'vendor:id,name,phone'])
            ->where('pickup_at', '>=', $today->copy()->subDays(14))
            ->orderByDesc('pickup_at')
            ->get();

        $laundryStats = [
            'pending' => $laundry->where('status', 'pending')->count(),
            'in_progress' => $laundry->whereIn('status', ['picked_up', 'in_wash'])->count(),
            'ready' => $laundry->whereIn('status', ['ready', 'returned'])->count(),
            'total_items' => (int) $laundry->sum('item_count'),
        ];

        // Active tickets + recently-resolved (30d) so their recorded repair cost
        // stays visible on the table (an in-flight ticket has no cost yet — it's
        // captured at resolve time). Active first, then resolved, newest within.
        $maintenance = MaintenanceTicket::query()
            ->with(['property:id,name', 'room:id,name', 'assignee:id,name,phone', 'reportedBy:id,name'])
            ->where(function ($q) use ($today) {
                $q->whereIn('status', ['open', 'in_progress'])
                    ->orWhere(function ($q2) use ($today) {
                        $q2->where('status', 'resolved')
                            ->where('resolved_at', '>=', $today->copy()->subDays(30));
                    });
            })
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            // Group active before resolved (stable — preserves the created_at order within each group).
            ->sortBy(fn ($m) => ['open' => 0, 'in_progress' => 1, 'resolved' => 2][$m->status] ?? 3)
            ->values();

        $maintenanceStats = [
            'open' => MaintenanceTicket::where('status', 'open')->count(),
            'in_progress' => MaintenanceTicket::where('status', 'in_progress')->count(),
            'high_priority' => MaintenanceTicket::where('priority', 'high')->whereIn('status', ['open', 'in_progress'])->count(),
            'resolved_30d' => MaintenanceTicket::where('status', 'resolved')->where('resolved_at', '>=', $today->copy()->subDays(30))->count(),
        ];

        $properties = Property::query()->orderBy('name')->get(['id', 'name', 'check_in_time', 'check_out_time']);

        // Active cleaners + laundry vendors for the "assign" dropdowns.
        $cleaners = Cleaner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);
        $laundryVendors = LaundryVendor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']);

        // Per-property check-in/out wall-clock times (HH:MM) — drives the
        // auto-default scheduled times in the create forms (client-side).
        $propertyTimes = $properties->mapWithKeys(fn ($p) => [$p->id => [
            'check_out' => substr((string) ($p->check_out_time ?: '12:00'), 0, 5),
            'check_in' => substr((string) ($p->check_in_time ?: '15:00'), 0, 5),
        ]]);

        $isBM = app()->getLocale() === 'ms';

        // Per-task copy-paste text (emoji-rich) — one "Copy text" button per
        // created schedule, keyed by task id. Copy keeps emoji (clipboard is
        // safe, unlike the old WhatsApp share-link handoff).
        $cleaningCopy = [];
        foreach ($todayTasks as $t) {
            $cleaningCopy[$t->id] = $this->cleaningTaskText($t, $isBM);
        }
        foreach ($upcoming as $t) {
            $cleaningCopy[$t->id] = $this->cleaningTaskText($t, $isBM);
        }

        $laundryCopy = [];
        foreach ($laundry as $t) {
            $laundryCopy[$t->id] = $this->laundryTaskText($t, $isBM);
        }

        $maintenanceCopy = [];
        foreach ($maintenance as $t) {
            $maintenanceCopy[$t->id] = $this->maintenanceTaskText($t, $isBM);
        }

        // WhatsApp "Share" deep links (wa.me) per task — pre-fills the message to
        // the assigned cleaner / laundry vendor / maintenance person. No saved
        // phone → link with no number so WhatsApp lets the host pick the chat.
        $cleaningShare = [];
        foreach ($todayTasks->concat($upcoming) as $t) {
            $cleaningShare[$t->id] = $this->waShareUrl($t->cleaner?->phone, $cleaningCopy[$t->id] ?? '');
        }
        $laundryShare = [];
        foreach ($laundry as $t) {
            $laundryShare[$t->id] = $this->waShareUrl($t->vendor?->phone, $laundryCopy[$t->id] ?? '');
        }
        $maintenanceShare = [];
        foreach ($maintenance as $t) {
            $maintenanceShare[$t->id] = $this->waShareUrl($t->assignee?->phone, $maintenanceCopy[$t->id] ?? '');
        }

        // History & costs tab — monthly + cumulative operating cost. Computed
        // only when that tab is open (cheap grouped aggregate over the tenant's
        // costed tasks; models are tenant-scoped via the global scope).
        $history = null;
        $selectedMonth = null;
        $monthDetail = null;
        if ($tab === 'history') {
            [$history, $selectedMonth, $monthDetail] = $this->buildCostHistory($request);
        }

        return view('tenant.housekeeping.index', [
            'tab' => in_array($tab, ['cleaning', 'laundry', 'maintenance', 'history']) ? $tab : 'cleaning',
            'history' => $history,
            'selectedMonth' => $selectedMonth,
            'monthDetail' => $monthDetail,
            'today' => $today,
            'todayTasks' => $todayTasks,
            'upcoming' => $upcoming,
            'cleaningStats' => $cleaningStats,
            'laundry' => $laundry,
            'laundryStats' => $laundryStats,
            'maintenance' => $maintenance,
            'maintenanceStats' => $maintenanceStats,
            'properties' => $properties,
            'cleaners' => $cleaners,
            'laundryVendors' => $laundryVendors,
            'propertyTimes' => $propertyTimes,
            'scheduleDate' => $scheduleDate,
            'cleaningCopy' => $cleaningCopy,
            'laundryCopy' => $laundryCopy,
            'maintenanceCopy' => $maintenanceCopy,
            'cleaningShare' => $cleaningShare,
            'laundryShare' => $laundryShare,
            'maintenanceShare' => $maintenanceShare,
        ]);
    }

    /** Build a wa.me share link (message pre-filled) to a staff phone. */
    private function waShareUrl(?string $phone, string $text): string
    {
        $digits = $phone ? preg_replace('/\D+/', '', $phone) : '';

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    /**
     * Monthly + cumulative operating cost (cleaning / laundry / maintenance),
     * plus the individual tasks for a drilled-into month (?month=YYYY-MM).
     * Grouping dates match the dashboard's "This month cost": cleaning by
     * scheduled_at, laundry by pickup_at, maintenance by resolved_at.
     *
     * @return array{0: array, 1: ?string, 2: ?array}
     */
    private function buildCostHistory(Request $request): array
    {
        $clean = CleaningTask::query()->whereNotNull('cost')
            ->with(['property:id,name', 'cleaner:id,name'])
            ->get(['id', 'property_id', 'cleaner_id', 'type', 'cost', 'scheduled_at', 'status']);
        $laund = LaundryTask::query()->whereNotNull('cost')
            ->with(['property:id,name', 'vendor:id,name'])
            ->get(['id', 'property_id', 'vendor_id', 'cost', 'pickup_at', 'status']);
        $maint = MaintenanceTicket::query()->whereNotNull('cost')->whereNotNull('resolved_at')
            ->with(['property:id,name'])
            ->get(['id', 'property_id', 'title', 'cost', 'resolved_at']);

        // month key => running category totals
        $buckets = [];
        $add = function (?Carbon $date, string $cat, float $cost) use (&$buckets) {
            if (! $date) {
                return;
            }
            $k = $date->format('Y-m');
            $buckets[$k] ??= ['cleaning' => 0.0, 'laundry' => 0.0, 'maintenance' => 0.0];
            $buckets[$k][$cat] += $cost;
        };
        foreach ($clean as $t) {
            $add($t->scheduled_at, 'cleaning', (float) $t->cost);
        }
        foreach ($laund as $t) {
            $add($t->pickup_at, 'laundry', (float) $t->cost);
        }
        foreach ($maint as $t) {
            $add($t->resolved_at, 'maintenance', (float) $t->cost);
        }

        ksort($buckets); // oldest first, to run the cumulative total
        $cumulative = 0.0;
        $rows = [];
        foreach ($buckets as $k => $v) {
            $total = $v['cleaning'] + $v['laundry'] + $v['maintenance'];
            $cumulative += $total;
            $rows[] = [
                'key' => $k,
                'label' => Carbon::createFromFormat('Y-m', $k)->translatedFormat('F Y'),
                'cleaning' => $v['cleaning'],
                'laundry' => $v['laundry'],
                'maintenance' => $v['maintenance'],
                'total' => $total,
                'cumulative' => $cumulative,
            ];
        }
        $grandTotal = $cumulative;
        $thisMonthKey = Carbon::today()->format('Y-m');
        $thisMonth = collect($rows)->firstWhere('key', $thisMonthKey);
        $rows = array_reverse($rows); // newest first for display

        $history = [
            'rows' => $rows,
            'grand_total' => $grandTotal,
            'this_month' => $thisMonth['total'] ?? 0.0,
            'this_month_label' => Carbon::today()->translatedFormat('F Y'),
            'cleaning_total' => collect($rows)->sum('cleaning'),
            'laundry_total' => collect($rows)->sum('laundry'),
            'maintenance_total' => collect($rows)->sum('maintenance'),
        ];

        // Drill-down: individual tasks for a chosen month.
        $selectedMonth = null;
        $monthDetail = null;
        $monthKeys = array_column($rows, 'key');
        $req = (string) $request->query('month', '');
        if ($req !== '' && in_array($req, $monthKeys, true)) {
            $selectedMonth = $req;
            $items = [];
            foreach ($clean->filter(fn ($t) => $t->scheduled_at?->format('Y-m') === $req) as $t) {
                $items[] = ['date' => $t->scheduled_at, 'cat' => 'cleaning', 'type' => $t->type,
                    'property' => $t->property?->name, 'who' => $t->cleaner?->name, 'status' => $t->status, 'cost' => (float) $t->cost];
            }
            foreach ($laund->filter(fn ($t) => $t->pickup_at?->format('Y-m') === $req) as $t) {
                $items[] = ['date' => $t->pickup_at, 'cat' => 'laundry', 'type' => 'laundry',
                    'property' => $t->property?->name, 'who' => $t->vendor?->name, 'status' => $t->status, 'cost' => (float) $t->cost];
            }
            foreach ($maint->filter(fn ($t) => $t->resolved_at?->format('Y-m') === $req) as $t) {
                $items[] = ['date' => $t->resolved_at, 'cat' => 'maintenance', 'type' => $t->title,
                    'property' => $t->property?->name, 'who' => null, 'status' => 'resolved', 'cost' => (float) $t->cost];
            }
            usort($items, fn ($a, $b) => ($a['date'] <=> $b['date']));
            $monthDetail = [
                'label' => Carbon::createFromFormat('Y-m', $req)->translatedFormat('F Y'),
                'items' => $items,
            ];
        }

        return [$history, $selectedMonth, $monthDetail];
    }

    /**
     * Generate the default cleaning + laundry schedule from the tenant's
     * upcoming confirmed/checked-in bookings. Host-initiated (a button on the
     * Housekeeping page), so it forces past the tier gate; idempotent via the
     * action's firstOrCreate, so re-running never duplicates.
     */
    public function generateSchedule(GenerateOperationalTasksForBooking $action)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $bookings = \App\Models\Booking::query()
            ->with('property')
            ->whereIn('status', [\App\Models\Booking::STATUS_CONFIRMED, \App\Models\Booking::STATUS_CHECKED_IN])
            ->whereDate('check_out', '>=', Carbon::today())
            ->orderBy('check_in')
            ->get();

        foreach ($bookings as $booking) {
            $action->execute($booking, force: true);
        }

        return back()->with('status', __(':count booking(s) scheduled for cleaning + laundry.', ['count' => $bookings->count()]));
    }

    /**
     * Cleaning type → human label (BM/EN) for the WhatsApp schedule.
     */
    private function cleaningTypeLabel(string $type, bool $isBM): string
    {
        $map = $isBM ? [
            'full' => 'Pembersihan penuh',
            'light' => 'Pembersihan ringkas',
            'deep' => 'Pencucian mendalam',
            'pool' => 'Kolam / luar',
            'post_event' => 'Selepas majlis',
            'pre_arrival' => 'Pra-pembersihan (habuk)',
        ] : [
            'full' => 'Full turnover',
            'light' => 'Light refresh',
            'deep' => 'Deep clean',
            'pool' => 'Pool / outdoor',
            'post_event' => 'Post-event',
            'pre_arrival' => 'Pre-arrival dusting',
        ];

        return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Format a task's notes for the WhatsApp schedule. A single-line note stays
     * inline ("Nota: text"); a multi-point note puts the label on its own line
     * and each point on its own indented line below it, e.g.
     *
     *   Nota:
     *   1. ...
     *   2. ...
     *
     * @return array<int, string>
     */
    private function formatScheduleNotes(string $notes, bool $isBM, bool $withEmoji = true): array
    {
        $textLabel = $isBM ? 'Nota:' : 'Notes:';
        $label = $withEmoji ? '📝 '.$textLabel : $textLabel;
        $notes = trim($notes);

        if ($notes === '') {
            return [];
        }

        if (! str_contains($notes, "\n")) {
            return ['   '.$label.' '.$notes];
        }

        $out = ['   '.$label];
        foreach (preg_split('/\r\n|\r|\n/', $notes) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue; // drop blank lines for a tidy point list
            }
            $out[] = '   '.$line;
        }

        return $out;
    }

    /**
     * Emoji-rich copy-paste text for a single cleaning task — drives the
     * per-task "Copy text" button (clipboard keeps emoji fine).
     */
    private function cleaningTaskText(CleaningTask $t, bool $isBM): string
    {
        $lines = [];
        $lines[] = '🧹 *'.($t->property?->name ?? '—').'*';
        if ($t->scheduled_at) {
            $lines[] = '📅 '.$t->scheduled_at->copy()->locale($isBM ? 'ms' : 'en')->isoFormat('dddd, D MMMM YYYY');
            $lines[] = '⏰ '.$t->scheduled_at->format('g:i A');
        }
        $lines[] = '🧽 '.$this->cleaningTypeLabel((string) $t->type, $isBM);
        // Crew size + how long the job should take (auto-scheduled turnovers).
        $crew = [];
        if ($t->cleaners_required) {
            $crew[] = '👥 '.((int) $t->cleaners_required).' '.($isBM ? 'pencuci' : ($t->cleaners_required > 1 ? 'cleaners' : 'cleaner'));
        }
        if ($t->duration_minutes) {
            $crew[] = '⏱️ ~'.rtrim(rtrim(number_format($t->duration_minutes / 60, 1), '0'), '.').($isBM ? ' jam' : 'h');
        }
        if ($crew) {
            $lines[] = implode('  ', $crew);
        }
        if ($t->room) {
            $lines[] = '🛏️ '.$t->room->name;
        }
        if ($t->cleaner) {
            $lines[] = '👤 '.$t->cleaner->name.($t->cleaner->phone ? ' ('.$t->cleaner->phone.')' : '');
        }
        if ($t->notes) {
            $lines = array_merge($lines, $this->formatScheduleNotes((string) $t->notes, $isBM, true));
        }

        return implode("\n", $lines);
    }

    /**
     * Emoji-rich copy-paste text for a single laundry batch — drives the
     * per-batch "Copy text" button.
     */
    private function laundryTaskText(LaundryTask $t, bool $isBM): string
    {
        $lines = [];
        $lines[] = '🧺 *'.($t->property?->name ?? '—').'*';
        if ($t->pickup_at) {
            $lines[] = '📅 '.$t->pickup_at->copy()->locale($isBM ? 'ms' : 'en')->isoFormat('dddd, D MMMM YYYY');
            $lines[] = '⏰ '.($isBM ? 'Ambil: ' : 'Pickup: ').$t->pickup_at->format('g:i A');
        }
        $lines[] = '📦 '.((int) $t->item_count).($isBM ? ' helai/item' : ' items');
        if ($t->expected_return_at) {
            $lines[] = '🔄 '.($isBM ? 'Jangka pulang: ' : 'Return: ').$t->expected_return_at->copy()->locale($isBM ? 'ms' : 'en')->isoFormat('ddd, D MMM');
        }
        $vendor = $t->vendor?->name ?? $t->vendor_name;
        if ($vendor) {
            $lines[] = '🏪 '.$vendor.($t->vendor?->phone ? ' ('.$t->vendor->phone.')' : '');
        }
        if ($t->notes) {
            $lines = array_merge($lines, $this->formatScheduleNotes((string) $t->notes, $isBM, true));
        }

        return implode("\n", $lines);
    }

    /**
     * Emoji-rich copy/share text for a single maintenance ticket.
     */
    private function maintenanceTaskText(MaintenanceTicket $t, bool $isBM): string
    {
        $priorityLabel = $isBM ? [
            'low' => 'Rendah', 'medium' => 'Sederhana', 'high' => 'Tinggi', 'urgent' => 'Segera',
        ] : [
            'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent',
        ];

        $lines = [];
        $lines[] = '🔧 *'.($t->title ?: ($isBM ? 'Kerja pembaikan' : 'Maintenance')).'*';
        $lines[] = '🏠 '.($t->property?->name ?? '—').($t->room ? ' · '.$t->room->name : '');
        if ($t->priority) {
            $lines[] = '⚠️ '.($isBM ? 'Keutamaan: ' : 'Priority: ').($priorityLabel[$t->priority] ?? ucfirst((string) $t->priority));
        }
        if ($t->description) {
            $lines = array_merge($lines, $this->formatScheduleNotes((string) $t->description, $isBM, true));
        }
        if ($t->assignee) {
            $lines[] = '👤 '.$t->assignee->name;
        }

        return implode("\n", $lines);
    }

    public function storeCleaning(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'type' => 'required|in:full,light,deep,pool,post_event,pre_arrival',
            'scheduled_at' => 'required|date',
            'cleaner_id' => 'nullable|exists:cleaners,id',
            'cleaners_required' => 'nullable|integer|min:1|max:20',
            'duration_hours' => 'nullable|numeric|min:0.5|max:24',
            'cost' => 'nullable|numeric|min:0|max:1000000',
            'notes' => 'nullable|string|max:2000',
        ]);

        CleaningTask::create([
            'tenant_id' => $tenant->id,
            'property_id' => $validated['property_id'],
            'type' => $validated['type'],
            'status' => CleaningTask::STATUS_PENDING,
            'cleaner_id' => $validated['cleaner_id'] ?? null,
            'cleaners_required' => $validated['cleaners_required'] ?? 1,
            'duration_minutes' => isset($validated['duration_hours']) ? (int) round($validated['duration_hours'] * 60) : null,
            'cost' => $validated['cost'] ?? null,
            'scheduled_at' => Carbon::parse($validated['scheduled_at']),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('tenant.housekeeping.index', ['tab' => 'cleaning'])
            ->with('status', __('Cleaning task scheduled.'));
    }

    public function storeLaundry(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'vendor_id' => 'nullable|exists:laundry_vendors,id',
            'pickup_at' => 'required|date',
            'expected_return_at' => 'nullable|date|after_or_equal:pickup_at',
            'item_count' => 'required|integer|min:1|max:9999',
            'cost' => 'nullable|numeric|min:0|max:1000000',
            'notes' => 'nullable|string|max:2000',
        ]);

        LaundryTask::create([
            'tenant_id' => $tenant->id,
            'property_id' => $validated['property_id'],
            'vendor_id' => $validated['vendor_id'] ?? null,
            'status' => LaundryTask::STATUS_PENDING,
            'cost' => $validated['cost'] ?? null,
            'pickup_at' => Carbon::parse($validated['pickup_at']),
            'expected_return_at' => isset($validated['expected_return_at'])
                ? Carbon::parse($validated['expected_return_at'])
                : null,
            'item_count' => $validated['item_count'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('tenant.housekeeping.index', ['tab' => 'laundry'])
            ->with('status', __('Laundry batch logged.'));
    }

    public function updateCleaning(Request $request, int $id)
    {
        $task = CleaningTask::findOrFail($id);

        $action = $request->input('action');
        $valid = ['start', 'complete', 'skip', 'edit'];
        abort_unless(in_array($action, $valid, true), 422, 'Invalid action');

        // Full edit — host adjusts the task details + freely sets the status.
        if ($action === 'edit') {
            $validated = $request->validate([
                'property_id' => 'required|exists:properties,id',
                'type' => 'required|in:full,light,deep,pool,post_event,pre_arrival',
                'status' => 'required|in:pending,in_progress,completed,skipped',
                'scheduled_at' => 'required|date',
                'cleaner_id' => 'nullable|exists:cleaners,id',
                'cleaners_required' => 'nullable|integer|min:1|max:20',
                'duration_hours' => 'nullable|numeric|min:0.5|max:24',
                'cost' => 'nullable|numeric|min:0|max:1000000',
                'notes' => 'nullable|string|max:2000',
            ]);

            $task->fill([
                'property_id' => $validated['property_id'],
                'type' => $validated['type'],
                'scheduled_at' => Carbon::parse($validated['scheduled_at']),
                'cleaner_id' => $validated['cleaner_id'] ?? null,
                'cleaners_required' => $validated['cleaners_required'] ?? $task->cleaners_required ?? 1,
                'duration_minutes' => isset($validated['duration_hours'])
                    ? (int) round($validated['duration_hours'] * 60)
                    : $task->duration_minutes,
                'cost' => $validated['cost'] ?? null,
                'notes' => $validated['notes'] ?? null,
                // A host edit takes ownership — stop the SOP from re-adjusting it.
                'auto_generated' => false,
            ]);
            $this->applyCleaningStatus($task, $validated['status']);
            $task->save();

            return back()->with('status', __('Cleaning task updated.'));
        }

        match ($action) {
            'start' => $task->update([
                'status' => CleaningTask::STATUS_IN_PROGRESS,
                'started_at' => $task->started_at ?? now(),
            ]),
            'complete' => $task->update([
                'status' => CleaningTask::STATUS_COMPLETED,
                'completed_at' => now(),
                'started_at' => $task->started_at ?? now()->subMinutes(30),
            ]),
            'skip' => $task->update([
                'status' => CleaningTask::STATUS_SKIPPED,
            ]),
        };

        return back()->with('status', __('Cleaning task updated.'));
    }

    /**
     * Set a cleaning task's status directly, stamping/clearing the matching
     * lifecycle timestamps so a free-form status edit stays consistent.
     */
    private function applyCleaningStatus(CleaningTask $task, string $status): void
    {
        $task->status = $status;

        if ($status === CleaningTask::STATUS_IN_PROGRESS) {
            $task->started_at = $task->started_at ?? now();
            $task->completed_at = null;
        } elseif ($status === CleaningTask::STATUS_COMPLETED) {
            $task->started_at = $task->started_at ?? now();
            $task->completed_at = $task->completed_at ?? now();
        } elseif ($status === CleaningTask::STATUS_PENDING) {
            $task->started_at = null;
            $task->completed_at = null;
        }
        // skipped: leave timestamps as-is
    }

    public function destroyCleaning(int $id)
    {
        CleaningTask::findOrFail($id)->delete();

        return back()->with('status', __('Cleaning task deleted.'));
    }

    public function updateLaundry(Request $request, int $id)
    {
        $task = LaundryTask::findOrFail($id);

        $action = $request->input('action');
        $valid = ['pickup', 'return', 'edit'];
        abort_unless(in_array($action, $valid, true), 422, 'Invalid action');

        // Full edit — host adjusts the batch details + freely sets the status.
        if ($action === 'edit') {
            $validated = $request->validate([
                'property_id' => 'required|exists:properties,id',
                'vendor_id' => 'nullable|exists:laundry_vendors,id',
                'status' => 'required|in:pending,picked_up,returned,cancelled',
                'pickup_at' => 'required|date',
                'expected_return_at' => 'nullable|date|after_or_equal:pickup_at',
                'item_count' => 'required|integer|min:1|max:9999',
                'cost' => 'nullable|numeric|min:0|max:1000000',
                'notes' => 'nullable|string|max:2000',
            ]);

            $task->fill([
                'property_id' => $validated['property_id'],
                'vendor_id' => $validated['vendor_id'] ?? null,
                'pickup_at' => Carbon::parse($validated['pickup_at']),
                'expected_return_at' => isset($validated['expected_return_at'])
                    ? Carbon::parse($validated['expected_return_at'])
                    : null,
                'item_count' => $validated['item_count'],
                'cost' => $validated['cost'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $this->applyLaundryStatus($task, $validated['status']);
            $task->save();

            return back()->with('status', __('Laundry batch updated.'));
        }

        match ($action) {
            'pickup' => $task->update([
                'status' => LaundryTask::STATUS_PICKED_UP,
                'picked_up_at' => now(),
            ]),
            'return' => $task->update([
                'status' => LaundryTask::STATUS_RETURNED,
                'returned_at' => now(),
            ]),
        };

        return back()->with('status', __('Laundry batch updated.'));
    }

    /**
     * Set a laundry batch's status directly, stamping/clearing the matching
     * lifecycle timestamps so a free-form status edit stays consistent.
     */
    private function applyLaundryStatus(LaundryTask $task, string $status): void
    {
        $task->status = $status;

        if ($status === LaundryTask::STATUS_PICKED_UP) {
            $task->picked_up_at = $task->picked_up_at ?? now();
            $task->returned_at = null;
        } elseif ($status === LaundryTask::STATUS_RETURNED) {
            $task->picked_up_at = $task->picked_up_at ?? now();
            $task->returned_at = $task->returned_at ?? now();
        } elseif ($status === LaundryTask::STATUS_PENDING) {
            $task->picked_up_at = null;
            $task->returned_at = null;
        }
        // cancelled: leave timestamps as-is
    }

    public function destroyLaundry(int $id)
    {
        LaundryTask::findOrFail($id)->delete();

        return back()->with('status', __('Laundry batch deleted.'));
    }

    public function printRunSheet()
    {
        $today = Carbon::today();
        $tenant = app(TenantContext::class)->current();

        $cleaning = CleaningTask::query()
            ->with(['property:id,name', 'room:id,name', 'assignee:id,name', 'booking:id,reference,guest_id', 'booking.guest:id,name'])
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $laundry = LaundryTask::query()
            ->with('property:id,name')
            ->whereDate('pickup_at', '<=', $today)
            ->whereIn('status', ['pending', 'picked_up'])
            ->orderBy('pickup_at')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tenant.housekeeping.print', [
            'today' => $today,
            'tenant' => $tenant,
            'cleaning' => $cleaning,
            'laundry' => $laundry,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('runsheet-'.$today->format('Y-m-d').'.pdf');
    }

    public function updateMaintenance(Request $request, int $id)
    {
        $ticket = MaintenanceTicket::findOrFail($id);

        $action = $request->input('action');
        $valid = ['start', 'resolve', 'close'];
        abort_unless(in_array($action, $valid, true), 422, 'Invalid action');

        $resolution = $request->input('resolution_notes');
        $cost = $request->input('cost');
        $cost = ($cost === null || $cost === '') ? null : (float) $cost;

        match ($action) {
            'start' => $ticket->update(['status' => MaintenanceTicket::STATUS_IN_PROGRESS]),
            'resolve' => $ticket->update([
                'status' => MaintenanceTicket::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolution_notes' => $resolution,
                'cost' => $cost,
            ]),
            'close' => $ticket->update(['status' => MaintenanceTicket::STATUS_CLOSED]),
        };

        return back()->with('status', __('Maintenance ticket updated.'));
    }
}
