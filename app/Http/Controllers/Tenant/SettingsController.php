<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $tenant?->loadMissing(['subscription', 'owner']);

        return view('tenant.settings.index', [
            'tenant' => $tenant,
        ]);
    }
}
