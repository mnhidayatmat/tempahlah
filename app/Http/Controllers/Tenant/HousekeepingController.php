<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CleaningTask;
use App\Models\LaundryTask;
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
        $weekEnd = $today->copy()->addDays(7);

        $todayTasks = CleaningTask::query()
            ->with(['property:id,name', 'room:id,name', 'assignee:id,name', 'booking:id,reference,guest_id', 'booking.guest:id,name'])
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $upcoming = CleaningTask::query()
            ->with(['property:id,name', 'assignee:id,name'])
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
            ->with(['property:id,name'])
            ->where('pickup_at', '>=', $today->copy()->subDays(14))
            ->orderByDesc('pickup_at')
            ->get();

        $laundryStats = [
            'pending' => $laundry->where('status', 'pending')->count(),
            'in_progress' => $laundry->whereIn('status', ['picked_up', 'in_wash'])->count(),
            'ready' => $laundry->whereIn('status', ['ready', 'returned'])->count(),
            'total_items' => (int) $laundry->sum('item_count'),
        ];

        $maintenance = MaintenanceTicket::query()
            ->with(['property:id,name', 'room:id,name', 'assignee:id,name', 'reportedBy:id,name'])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $maintenanceStats = [
            'open' => MaintenanceTicket::where('status', 'open')->count(),
            'in_progress' => MaintenanceTicket::where('status', 'in_progress')->count(),
            'high_priority' => MaintenanceTicket::where('priority', 'high')->whereIn('status', ['open', 'in_progress'])->count(),
            'resolved_30d' => MaintenanceTicket::where('status', 'resolved')->where('resolved_at', '>=', $today->copy()->subDays(30))->count(),
        ];

        $properties = Property::query()->orderBy('name')->get(['id', 'name']);

        return view('tenant.housekeeping.index', [
            'tab' => in_array($tab, ['cleaning', 'laundry', 'maintenance']) ? $tab : 'cleaning',
            'today' => $today,
            'todayTasks' => $todayTasks,
            'upcoming' => $upcoming,
            'cleaningStats' => $cleaningStats,
            'laundry' => $laundry,
            'laundryStats' => $laundryStats,
            'maintenance' => $maintenance,
            'maintenanceStats' => $maintenanceStats,
            'properties' => $properties,
        ]);
    }

    public function storeCleaning(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'type' => 'required|in:full,light,deep,pool,post_event',
            'scheduled_at' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        CleaningTask::create([
            'tenant_id' => $tenant->id,
            'property_id' => $validated['property_id'],
            'type' => $validated['type'],
            'status' => CleaningTask::STATUS_PENDING,
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
            'vendor_name' => 'nullable|string|max:120',
            'pickup_at' => 'required|date',
            'expected_return_at' => 'nullable|date|after_or_equal:pickup_at',
            'item_count' => 'required|integer|min:1|max:9999',
            'notes' => 'nullable|string|max:500',
        ]);

        LaundryTask::create([
            'tenant_id' => $tenant->id,
            'property_id' => $validated['property_id'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'status' => LaundryTask::STATUS_PENDING,
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
        $valid = ['start', 'complete', 'skip'];
        abort_unless(in_array($action, $valid, true), 422, 'Invalid action');

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

    public function updateLaundry(Request $request, int $id)
    {
        $task = LaundryTask::findOrFail($id);

        $action = $request->input('action');
        $valid = ['pickup', 'return'];
        abort_unless(in_array($action, $valid, true), 422, 'Invalid action');

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

        match ($action) {
            'start' => $ticket->update(['status' => MaintenanceTicket::STATUS_IN_PROGRESS]),
            'resolve' => $ticket->update([
                'status' => MaintenanceTicket::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolution_notes' => $resolution,
            ]),
            'close' => $ticket->update(['status' => MaintenanceTicket::STATUS_CLOSED]),
        };

        return back()->with('status', __('Maintenance ticket updated.'));
    }
}
