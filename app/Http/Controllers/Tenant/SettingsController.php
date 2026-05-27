<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = app(TenantContext::class)->current();
        $tenant?->loadMissing(['subscription', 'owner']);

        $properties = Property::query()
            ->with(['rooms:id,property_id,base_price'])
            ->orderByDesc('created_at')
            ->get();

        return view('tenant.settings.index', [
            'tenant'     => $tenant,
            'properties' => $properties,
        ]);
    }

    public function update(Request $request)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403, 'No tenant context');

        $validated = $request->validate([
            'business_name'   => 'required|string|max:120',
            'business_email'  => 'required|email|max:160',
            'business_phone'  => 'nullable|string|max:32',
            'ssm_number'      => 'nullable|string|max:32',
            'motac_license'   => 'nullable|string|max:64',
            'sst_registered'  => 'sometimes|boolean',
            'sst_rate'        => 'nullable|numeric|min:0|max:1',
            'default_locale'  => 'required|in:ms,en',
            'primary_color'   => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color'    => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'primary_color.regex'   => __('Pick a valid hex color (e.g. #d97757).'),
            'secondary_color.regex' => __('Pick a valid hex color (e.g. #a8401e).'),
            'accent_color.regex'    => __('Pick a valid hex color (e.g. #d4a437).'),
        ]);

        $validated['sst_registered'] = $request->boolean('sst_registered');
        if (! $validated['sst_registered']) {
            $validated['sst_rate'] = 0;
        } elseif (empty($validated['sst_rate'])) {
            $validated['sst_rate'] = 0.08;
        }

        foreach (['primary_color', 'secondary_color', 'accent_color'] as $key) {
            $validated[$key] = ! empty($validated[$key])
                ? '#'.strtolower(ltrim($validated[$key], '#'))
                : null;
        }
        // primary_color column is NOT NULL — fall back to the platform default
        // when the tenant clears it. secondary/accent stay nullable.
        $validated['primary_color'] ??= \App\Models\Tenant::THEME_DEFAULTS['primary'];

        $tenant->fill($validated)->save();

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', __('Settings saved.'));
    }
}
