<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PricingRule;
use App\Models\Property;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PricingRuleController extends Controller
{
    public function store(Request $request, Property $property)
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant && $property->tenant_id === $tenant->id, 403);

        // Bulk-create path for the public-holiday picker. When the form
        // sends `holiday_picks_json` (a JSON array of {date, name}), we
        // create ONE PricingRule per selected date, all sharing the same
        // adjustment/priority/active/room_id. Rule names get appended
        // with " — {holiday name}" so each row is identifiable in the
        // rules list.
        if ($request->input('rule_type') === PricingRule::TYPE_HOLIDAY
            && filled($request->input('holiday_picks_json'))) {
            return $this->bulkStoreHolidayRules($request, $tenant, $property);
        }

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

    /**
     * Bulk-create one PricingRule per checked holiday in the picker.
     * Shared adjustment + name prefix + room scope + active state.
     * All-or-nothing transaction — any validation failure rolls back.
     */
    protected function bulkStoreHolidayRules(Request $request, $tenant, Property $property)
    {
        $picks = json_decode((string) $request->input('holiday_picks_json'), true);
        if (! is_array($picks) || count($picks) === 0) {
            return back()->withInput()->withErrors([
                'holiday_picks_json' => __('Select at least one holiday from the picker, or switch to a different rule type.'),
            ]);
        }

        // Validate shared fields (the date_from/date_to validation in
        // validateRule() doesnt apply — each rule gets its date from
        // the holiday pick, not the form's date_from input).
        $shared = $request->validate([
            'room_id'          => 'nullable|integer|exists:rooms,id',
            'name'             => 'required|string|max:60', // leaves room for the " — {holiday}" suffix to fit in the 80-char column
            'adjustment_type'  => 'required|in:percent,flat,override',
            'adjustment_value' => 'required|numeric',
            'priority'         => 'nullable|integer|min:1|max:999',
        ]);
        $active = $request->boolean('active', true);

        $created = 0;
        DB::transaction(function () use ($picks, $shared, $tenant, $property, $active, &$created) {
            foreach ($picks as $pick) {
                $date = (string) ($pick['date'] ?? '');
                $name = (string) ($pick['name'] ?? '');
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $name === '') {
                    continue; // skip malformed entries
                }

                // Idempotency: if a rule with the same name+date already
                // exists for this property, skip — lets the tenant re-
                // open the form and pick "Select all" without dupes.
                $exists = PricingRule::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('property_id', $property->id)
                    ->where('rule_type', PricingRule::TYPE_HOLIDAY)
                    ->where('date_from', $date)
                    ->where('date_to', $date)
                    ->where('name', $shared['name'].' — '.$name)
                    ->exists();
                if ($exists) continue;

                PricingRule::create([
                    'tenant_id'    => $tenant->id,
                    'property_id'  => $property->id,
                    'room_id'      => $shared['room_id'] ?? null,
                    'name'         => $shared['name'].' — '.$name,
                    'rule_type'    => PricingRule::TYPE_HOLIDAY,
                    'weekday_mask' => null,
                    'date_from'    => $date,
                    'date_to'      => $date,
                    'adjustment_type'  => $shared['adjustment_type'],
                    'adjustment_value' => $shared['adjustment_value'],
                    'priority'     => (int) ($shared['priority'] ?? 100),
                    'active'       => $active,
                ]);
                $created++;
            }
        });

        $msg = $created === 0
            ? __('No new rules created — all selected holidays already have a rule with this name.')
            : trans_choice('{1} :count holiday rule added.|[2,*] :count holiday rules added.', $created, ['count' => $created]);

        return redirect()
            ->route('tenant.properties.show', ['id' => $property->id, 'tab' => 'pricing'])
            ->with('status', $msg);
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
            'rule_type'        => 'required|in:weekend,holiday,school_holiday,season,custom',
            'weekday_mask'     => 'nullable|array',
            'weekday_mask.*'   => 'integer|min:0|max:6',
            // Date range is mandatory for holiday / school holiday / season rules —
            // those types are MEANINGLESS without a date window. Weekend/custom
            // can leave them blank (rule runs forever, gated only by weekdays).
            'date_from'        => 'required_if:rule_type,holiday,school_holiday,season|nullable|date',
            'date_to'          => 'required_if:rule_type,holiday,school_holiday,season|nullable|date|after_or_equal:date_from',
            'adjustment_type'  => 'required|in:percent,flat,override',
            'adjustment_value' => 'required|numeric',
            'priority'         => 'nullable|integer|min:1|max:999',
        ], [
            'date_from.required_if' => __('Date from is required for this rule type.'),
            'date_to.required_if'   => __('Date to is required for this rule type.'),
        ]);
    }
}
