<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenancy\CreateTenantAndOwner;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $reservedSlugs = CreateTenantAndOwner::reservedSlugs();

        $validated = $request->validate([
            'business_name'   => 'required|string|max:120',
            'business_email'  => 'required|email|max:160',
            'business_phone'  => 'nullable|string|max:32',
            'ssm_number'      => 'nullable|string|max:32',
            'motac_license'   => 'nullable|string|max:64',
            'slug'            => [
                'required', 'string', 'min:2', 'max:60',
                // Lowercase letters, digits, hyphens. Hyphens can't be at the
                // edges and can't double up. Same shape DNS will tolerate.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tenants', 'slug')->ignore($tenant->id),
                Rule::notIn($reservedSlugs),
            ],
            'sst_registered'  => 'sometimes|boolean',
            'sst_rate'        => 'nullable|numeric|min:0|max:1',
            'default_locale'  => 'required|in:ms,en',
            'full_payment_days_before' => 'required|integer|min:0|max:60',
            'fee_payment_hours'        => 'required|integer|min:1|max:336',
            'cancel_balance_on'        => ['required', Rule::in([
                Tenant::CANCEL_BALANCE_DUE_DATE,
                Tenant::CANCEL_BALANCE_CHECK_IN,
            ])],
            'refund_policy'            => 'nullable|string|max:2000',
            'primary_color'   => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color'    => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'slug.regex'   => __('Slug can only contain lowercase letters, numbers, and single hyphens (e.g. wafa-homestay).'),
            'slug.unique'  => __('That slug is already taken by another homestay. Please pick another.'),
            'slug.not_in'  => __('That slug is reserved (it conflicts with a system subdomain). Please pick another.'),
            'primary_color.regex'   => __('Pick a valid hex color (e.g. #2596c6).'),
            'secondary_color.regex' => __('Pick a valid hex color (e.g. #2cb8c4).'),
            'accent_color.regex'    => __('Pick a valid hex color (e.g. #e8b94a).'),
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

        $oldSlug = $tenant->slug;
        $tenant->fill($validated)->save();

        $msg = $oldSlug !== $tenant->slug
            ? __('Settings saved. Your booking page is now :url — the old :old.tempahlah.com address no longer works.', [
                'url' => str_replace(['https://', 'http://'], '', $tenant->publicUrl()),
                'old' => $oldSlug,
              ])
            : __('Settings saved.');

        return redirect()
            ->route('tenant.settings.index')
            ->with('status', $msg);
    }
}
