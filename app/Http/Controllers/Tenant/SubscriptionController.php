<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;

class SubscriptionController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $plan = $tenant?->subscription?->plan ?? 'free';
        return view('tenant.subscription.index', compact('plan', 'tenant'));
    }
}
