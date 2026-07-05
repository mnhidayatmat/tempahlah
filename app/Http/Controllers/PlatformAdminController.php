<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * In-app Platform Admin area (/dashboard/admin) — the "super tenant" view a
 * flagged user toggles into from the normal dashboard. Shows the Free vs Paid
 * subscriber breakdown + a cross-tenant list. Gated by the platform.admin
 * middleware. Tenant / Subscription models carry no tenant global scope, so
 * these queries see every tenant regardless of the signed-in user's own tenant.
 */
class PlatformAdminController extends Controller
{
    public function overview(Request $request)
    {
        return view('platform.overview', array_merge(
            $this->stats(),
            ['tenants' => $this->tenantList($request)],
        ));
    }

    /**
     * Aggregate subscription counts + MRR in one grouped pass.
     *
     * @return array<string, mixed>
     */
    protected function stats(): array
    {
        $rows = Subscription::query()
            ->selectRaw('plan, status, COUNT(*) as c, COALESCE(SUM(monthly_amount), 0) as amt')
            ->groupBy('plan', 'status')
            ->get();

        $count = fn (?string $plan = null, ?string $status = null) => (int) $rows
            ->when($plan !== null, fn ($r) => $r->where('plan', $plan))
            ->when($status !== null, fn ($r) => $r->where('status', $status))
            ->sum('c');

        $totalTenants = Tenant::query()->count();
        $free       = $count(Subscription::PLAN_FREE);
        $paidActive = $count(Subscription::PLAN_PAID, Subscription::STATUS_ACTIVE);
        $trialing   = $count(null, Subscription::STATUS_TRIALING);
        $pastDue    = $count(null, Subscription::STATUS_PAST_DUE);
        $cancelled  = $count(null, Subscription::STATUS_CANCELLED);

        $mrr = (float) $rows
            ->where('plan', Subscription::PLAN_PAID)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->sum('amt');

        return [
            'totalTenants' => $totalTenants,
            'free'         => $free,
            'paidActive'   => $paidActive,
            'trialing'     => $trialing,
            'subscribed'   => $paidActive + $trialing,
            'pastDue'      => $pastDue,
            'cancelled'    => $cancelled,
            'mrr'          => $mrr,
        ];
    }

    /**
     * Paginated cross-tenant list with each tenant's plan + status. Optional
     * ?plan=free|paid and ?q= (business name / email) filters.
     */
    protected function tenantList(Request $request)
    {
        $plan = in_array($request->query('plan'), [Subscription::PLAN_FREE, Subscription::PLAN_PAID], true)
            ? $request->query('plan')
            : null;
        $q = trim((string) $request->query('q', ''));

        return Tenant::query()
            ->with(['subscription', 'owner:id,name,email'])
            ->when($plan, fn ($query) => $query->whereHas('subscription', fn ($s) => $s->where('plan', $plan)))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('business_name', 'like', "%{$q}%")
                ->orWhere('business_email', 'like', "%{$q}%")))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
    }
}
