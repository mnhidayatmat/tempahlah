<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status'); // null|succeeded|pending
        $start = Carbon::now()->subDays(30);

        $payments = Payment::query()
            ->with(['booking:id,public_id,reference,guest_id,property_id', 'booking.guest:id,name', 'booking.property:id,name'])
            ->where('created_at', '>=', $start)
            ->when($filter === 'succeeded', fn ($q) => $q->where('status', Payment::STATUS_SUCCEEDED))
            ->when($filter === 'pending', fn ($q) => $q->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING]))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $allRecent = Payment::query()
            ->where('created_at', '>=', $start)
            ->get(['amount', 'gateway_fee', 'status', 'method', 'created_at']);

        $collected = (float) $allRecent->where('status', Payment::STATUS_SUCCEEDED)->sum('amount');
        $fees = (float) $allRecent->sum('gateway_fee');
        $pending = (float) $allRecent->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])->sum('amount');
        $pendingCount = $allRecent->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])->count();

        return view('tenant.payments.index', [
            'payments' => $payments,
            'filter' => $filter,
            'collected' => $collected,
            'fees' => $fees,
            'pending' => $pending,
            'pendingCount' => $pendingCount,
            'netPayout' => $collected - $fees,
            'totalCount' => $allRecent->count(),
        ]);
    }
}
