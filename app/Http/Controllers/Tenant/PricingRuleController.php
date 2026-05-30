<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class PricingRuleController extends Controller
{
    public function store(Request $request, Property $property)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant && $property->tenant_id === $tenant->id, 403);

        $validated = $this->validateRule($request);

        PricingRule::create([
            'tenant_id'    => $tenant->id,
            'property_id'  => $property->id,
            'room_id'      => $validated['room_id'] ?? null,
            'name'         => $validated['name'],
            'rule_type'    => $validated['rule_type'],
            'weekday_mask' => $validated['weekday_mask'] ?? null,
            'date_from'    => $validated['date_from'] ?? null,
            'date_to'      => $validated['date_to'] ?? null,
            'adjustment_type'  => $validated['adjustment_type'],
            'adjustment_value' => $validated['adjustment_value'],
            'priority'     => (int) ($validated['priority'] ?? 100),
            'active'       => $request->boolean('active', true),
        ]);

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id, 'tab' => 'pricing'])
            ->with('status', __('Pricing rule ":name" added.', ['name' => $validated['name']]));
    }

    public function update(Request $request, Property $property, PricingRule $rule)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant && $rule->tenant_id === $tenant->id && $rule->property_id === $property->id, 403);

        $validated = $this->validateRule($request);

        $rule->update([
            'room_id'      => $validated['room_id'] ?? null,
            'name'         => $validated['name'],
            'rule_type'    => $validated['rule_type'],
            'weekday_mask' => $validated['weekday_mask'] ?? null,
            'date_from'    => $validated['date_from'] ?? null,
            'date_to'      => $validated['date_to'] ?? null,
            'adjustment_type'  => $validated['adjustment_type'],
            'adjustment_value' => $validated['adjustment_value'],
            'priority'     => (int) ($validated['priority'] ?? 100),
            'active'       => $request->boolean('active', true),
        ]);

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id, 'tab' => 'pricing'])
            ->with('status', __('Pricing rule ":name" updated.', ['name' => $validated['name']]));
    }

    public function destroy(Property $property, PricingRule $rule)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant && $rule->tenant_id === $tenant->id && $rule->property_id === $property->id, 403);

        $name = $rule->name;
        $rule->delete();

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id, 'tab' => 'pricing'])
            ->with('status', __('Pricing rule ":name" deleted.', ['name' => $name]));
    }

    public function toggle(Property $property, PricingRule $rule)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant && $rule->tenant_id === $tenant->id && $rule->property_id === $property->id, 403);

        $rule->update(['active' => ! $rule->active]);

        return back()->with('status', __(':name :state.', [
            'name'  => $rule->name,
            'state' => $rule->active ? __('enabled') : __('disabled'),
        ]));
    }

    protected function validateRule(Request $request): array
    {
        return $request->validate([
            'room_id'          => 'nullable|integer|exists:rooms,id',
            'name'             => 'required|string|max:80',
            'rule_type'        => 'required|in:weekend,holiday,season,custom',
            'weekday_mask'     => 'nullable|array',
            'weekday_mask.*'   => 'integer|min:0|max:6',
            'date_from'        => 'nullable|date',
            'date_to'          => 'nullable|date|after_or_equal:date_from',
            'adjustment_type'  => 'required|in:percent,flat,override',
            'adjustment_value' => 'required|numeric',
            'priority'         => 'nullable|integer|min:1|max:999',
        ]);
    }
}
