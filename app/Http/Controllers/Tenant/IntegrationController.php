<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;

class IntegrationController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        return view('tenant.integrations.index', compact('tenant'));
    }
}
