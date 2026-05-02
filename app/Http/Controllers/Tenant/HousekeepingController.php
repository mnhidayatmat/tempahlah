<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CleaningTask;
use App\Models\LaundryTask;
use App\Models\MaintenanceTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        ]);
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
