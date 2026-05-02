<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

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

    public function update(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'business_name' => 'required|string|max:120',
            'business_email' => 'required|email|max:160',
            'business_phone' => 'nullable|string|max:32',
            'ssm_number' => 'nullable|string|max:32',
            'motac_license' => 'nullable|string|max:64',
            'sst_registered' => 'sometimes|boolean',
            'sst_rate' => 'nullable|numeric|min:0|max:1',
            'default_locale' => 'required|in:ms,en',
        ]);

        $validated['sst_registered'] = $request->boolean('sst_registered');
        if (! $validated['sst_registered']) {
            $validated['sst_rate'] = 0;
        } elseif (empty($validated['sst_rate'])) {
            $validated['sst_rate'] = 0.08;
        }

        $tenant->fill($validated)->save();

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', __('Settings saved.'));
    }
}
